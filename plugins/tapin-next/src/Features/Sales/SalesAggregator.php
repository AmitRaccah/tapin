<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\Commission;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;
use WC_DateTime;
use WC_Order;
use WC_Order_Item_Product;

final class SalesAggregator
{
    /**
     * @param array<int,int> $orderIds
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    public function aggregate(array $orderIds, int $producerId, int $affiliateId, array $options = []): array
    {
        if (!function_exists('wc_get_order')) {
            return [];
        }

        $rows = [];
        $thumbCache = [];
        $eventTsCache = [];
        $authorCache = [];
        $commissionCache = [];
        $referralCache = [];

        $includeZero = !empty($options['include_zero']);
        $productStatus = isset($options['product_status']) ? (string) $options['product_status'] : 'publish';
        $canCheckReferrals = $affiliateId > 0 && function_exists('afwc_get_product_affiliate_url');

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId <= 0) {
                continue;
            }
            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }
            $orderTs = $this->resolveOrderTimestamp($order);
            $wasReferred = $this->orderHasReferral($orderId, $affiliateId, $canCheckReferrals, $referralCache);

            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $productId = (int) $item->get_product_id();
                if ($productId <= 0) {
                    continue;
                }

                $authorId = $this->resolveAuthorId($productId, $authorCache);
                if ($authorId !== $producerId) {
                    continue;
                }

                if (!isset($rows[$productId])) {
                    $rows[$productId] = $this->createEventRow(
                        $productId,
                        $authorId,
                        $thumbCache,
                        $eventTsCache,
                        $commissionCache
                    );
                } elseif (empty($rows[$productId]['commission_meta'])) {
                    $rows[$productId]['commission_meta'] = $this->resolveCommissionMeta($productId, $commissionCache);
                }

                $qty = (int) $item->get_quantity();
                $lineTotal = (float) $item->get_total();

                $rows[$productId]['qty'] += $qty;
                $rows[$productId]['sum'] += $lineTotal;

                if ($wasReferred) {
                    $rows[$productId]['ref_qty'] += $qty;
                    $rows[$productId]['ref_sum'] += $lineTotal;

                    $commission = Commission::calculate($rows[$productId]['commission_meta'] ?? [], $lineTotal, $qty);
                    if ($commission > 0) {
                        $rows[$productId]['ref_commission'] += $commission;
                    }
                }

                $this->accumulateTicketStats($rows[$productId], $item, $wasReferred, $orderTs);
            }
        }

        if ($includeZero) {
            $this->appendZeroSalesProducts(
                $rows,
                $producerId,
                $productStatus,
                $thumbCache,
                $eventTsCache,
                $commissionCache,
                $authorCache
            );
        }

        uasort($rows, static function (array $a, array $b): int {
            $dateDiff = ($b['event_ts'] ?? 0) <=> ($a['event_ts'] ?? 0);
            if ($dateDiff !== 0) {
                return $dateDiff;
            }
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    private function resolveOrderTimestamp(WC_Order $order): int
    {
        $created = $order->get_date_created();
        if ($created instanceof WC_DateTime) {
            return $created->getTimestamp();
        }
        return 0;
    }

    /**
     * @param array<int,string> $thumbCache
     * @param array<int,int> $eventTsCache
     * @param array<int,array<string,mixed>> $commissionCache
     */
    private function createEventRow(
        int $productId,
        int $authorId,
        array &$thumbCache,
        array &$eventTsCache,
        array &$commissionCache
    ): array {
        $ticketTypes = TicketTypesRepository::get($productId);
        $ticketIndex = $this->indexTicketTypes($ticketTypes);
        $regular = $this->resolveRegularTicket($ticketTypes);
        $eventTs = $this->resolveEventTimestamp($productId, $eventTsCache);
        $commissionMeta = $this->resolveCommissionMeta($productId, $commissionCache);
        $format = get_option('date_format') . ' H:i';
        $eventLabel = $eventTs > 0 ? Time::fmtLocal($eventTs, $format) : '';

        return [
            'name'               => get_the_title($productId),
            'qty'                => 0,
            'sum'                => 0.0,
            'view'               => get_permalink($productId),
            'thumb'              => $this->resolveThumb($productId, $thumbCache),
            'author_id'          => $authorId,
            'ref_qty'            => 0,
            'ref_sum'            => 0.0,
            'ref_commission'     => 0.0,
            'event_ts'           => $eventTs,
            'event_date_label'   => $eventLabel,
            'ticket_index'       => $ticketIndex,
            'regular_type_id'    => $regular['id'],
            'regular_type_label' => $regular['label'],
            'commission_meta'    => $commissionMeta,
            'stats'              => [
                'regular_total'     => 0,
                'regular_affiliate' => 0,
                'regular_direct'    => 0,
                'special_types'     => [],
                'windows'           => $this->buildWindowBuckets($productId, $ticketTypes),
            ],
        ];
    }

    /**
     * @param array<int,string> $thumbCache
     */
    private function resolveThumb(int $productId, array &$thumbCache): string
    {
        if (!array_key_exists($productId, $thumbCache)) {
            $url = get_the_post_thumbnail_url($productId, 'woocommerce_thumbnail');
            if (!$url && function_exists('wc_placeholder_img_src')) {
                $url = wc_placeholder_img_src();
            }
            if (!$url) {
                $url = includes_url('images/media/default.png');
            }
            $thumbCache[$productId] = (string) $url;
        }

        return $thumbCache[$productId];
    }

    /**
     * @param array<int,int> $eventTsCache
     */
    private function resolveEventTimestamp(int $productId, array &$eventTsCache): int
    {
        if (!array_key_exists($productId, $eventTsCache)) {
            $eventTsCache[$productId] = Time::productEventTs($productId);
        }
        return (int) $eventTsCache[$productId];
    }

    /**
     * @param array<int,array<string,mixed>> $commissionCache
     * @return array{type:string,amount:float}
     */
    private function resolveCommissionMeta(int $productId, array &$commissionCache): array
    {
        if (!array_key_exists($productId, $commissionCache)) {
            $type = get_post_meta($productId, MetaKeys::PRODUCER_AFF_TYPE, true);
            $amount = get_post_meta($productId, MetaKeys::PRODUCER_AFF_AMOUNT, true);

            $commissionCache[$productId] = [
                'type'   => in_array($type, ['percent', 'flat'], true) ? (string) $type : '',
                'amount' => is_numeric($amount) ? (float) $amount : 0.0,
            ];
        }

        return $commissionCache[$productId];
    }

    /**
     * @param array<int,int> $authorCache
     */
    private function resolveAuthorId(int $productId, array &$authorCache): int
    {
        if (!array_key_exists($productId, $authorCache)) {
            $authorCache[$productId] = (int) get_post_field('post_author', $productId);
        }
        return $authorCache[$productId];
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array<int,array<string,int>>
     */
    private function buildWindowBuckets(int $productId, array $ticketTypes): array
    {
        $windows = SaleWindowsRepository::get($productId, $ticketTypes);
        $buckets = [];
        foreach ($windows as $window) {
            $buckets[] = [
                'start'     => (int) ($window['start'] ?? 0),
                'end'       => (int) ($window['end'] ?? 0),
                'affiliate' => 0,
                'direct'    => 0,
            ];
        }
        return $buckets;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $thumbCache
     * @param array<int,int> $eventTsCache
     * @param array<int,array<string,mixed>> $commissionCache
     * @param array<int,int> $authorCache
     */
    private function appendZeroSalesProducts(
        array &$rows,
        int $producerId,
        string $productStatus,
        array &$thumbCache,
        array &$eventTsCache,
        array &$commissionCache,
        array &$authorCache
    ): void {
        $statusArg = strtolower($productStatus) === 'any'
            ? 'any'
            : array_filter(array_map('trim', explode(',', $productStatus)));

        $products = get_posts([
            'post_type'      => 'product',
            'author'         => $producerId,
            'post_status'    => $statusArg === 'any' ? 'any' : $statusArg,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        if (!is_array($products)) {
            return;
        }

        foreach ($products as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }
            if (!isset($rows[$productId])) {
                $authorId = $this->resolveAuthorId($productId, $authorCache) ?: $producerId;
                $rows[$productId] = $this->createEventRow(
                    $productId,
                    $authorId,
                    $thumbCache,
                    $eventTsCache,
                    $commissionCache
                );
            } elseif (empty($rows[$productId]['commission_meta'])) {
                $rows[$productId]['commission_meta'] = $this->resolveCommissionMeta($productId, $commissionCache);
            }
        }
    }

    /**
     * @param array<int,bool> $referralCache
     */
    private function orderHasReferral(
        int $orderId,
        int $affiliateId,
        bool $canCheckReferrals,
        array &$referralCache
    ): bool {
        if (!$canCheckReferrals || $orderId <= 0) {
            return false;
        }
        if (array_key_exists($orderId, $referralCache)) {
            return $referralCache[$orderId];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'afwc_referrals';
        $referralCache[$orderId] = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT referral_id FROM {$table} WHERE post_id = %d AND affiliate_id = %d AND status <> %s LIMIT 1",
                $orderId,
                $affiliateId,
                'rejected'
            )
        );

        return $referralCache[$orderId];
    }

    private function accumulateTicketStats(array &$eventRow, WC_Order_Item_Product $item, bool $wasReferred, int $orderTs): void
    {
        $tickets = $this->extractTicketsFromItem($item);
        if ($tickets === []) {
            $count = max(1, (int) $item->get_quantity());
            $tickets = array_fill(0, $count, ['ticket_type' => '', 'ticket_type_label' => '']);
        }

        foreach ($tickets as $ticket) {
            $typeId = isset($ticket['ticket_type']) ? (string) $ticket['ticket_type'] : '';
            $label = isset($ticket['ticket_type_label']) ? (string) $ticket['ticket_type_label'] : '';
            $resolved = $this->resolveTicketTypeId($typeId, $label, $eventRow['ticket_index'] ?? []);

            if ($this->isRegularTicket(
                $resolved,
                $label,
                (string) ($eventRow['regular_type_id'] ?? ''),
                (string) ($eventRow['regular_type_label'] ?? '')
            )) {
                $eventRow['stats']['regular_total'] = (int) ($eventRow['stats']['regular_total'] ?? 0) + 1;
                if ($wasReferred) {
                    $eventRow['stats']['regular_affiliate'] = (int) ($eventRow['stats']['regular_affiliate'] ?? 0) + 1;
                } else {
                    $eventRow['stats']['regular_direct'] = (int) ($eventRow['stats']['regular_direct'] ?? 0) + 1;
                }
                $this->incrementWindowBuckets($eventRow['stats']['windows'], $orderTs, $wasReferred);
                continue;
            }

            $key = $resolved !== '' ? 'id:' . $resolved : 'label:' . md5($label ?: wp_json_encode($ticket));
            if (!isset($eventRow['stats']['special_types'][$key])) {
                $fallbackLabel = '';
                if ($resolved !== '' && isset($eventRow['ticket_index'][$resolved]['name'])) {
                    $fallbackLabel = (string) $eventRow['ticket_index'][$resolved]['name'];
                }
                $eventRow['stats']['special_types'][$key] = [
                    'label' => $label !== '' ? $label : $fallbackLabel,
                    'qty'   => 0,
                ];
            }
            $eventRow['stats']['special_types'][$key]['qty'] = (int) ($eventRow['stats']['special_types'][$key]['qty'] ?? 0) + 1;
        }
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractTicketsFromItem(WC_Order_Item_Product $item): array
    {
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded !== []) {
            return array_map([$this, 'normalizeTicketMeta'], $decoded);
        }

        $legacy = (string) $item->get_meta('Tapin Attendees', true);
        if ($legacy !== '') {
            $legacyDecoded = AttendeeSecureStorage::decrypt($legacy);
            if ($legacyDecoded !== []) {
                return array_map([$this, 'normalizeTicketMeta'], $legacyDecoded);
            }
        }

        $order = $item->get_order();
        if ($order instanceof WC_Order) {
            $aggregate = $order->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                return array_map([$this, 'normalizeTicketMeta'], $aggregateDecoded);
            }
        }

        $fallback = [];
        $summaryKeys = AttendeeFields::summaryKeys();
        foreach ($item->get_formatted_meta_data('') as $meta) {
            $label = (string) $meta->key;
            if (
                strpos($label, "\u{05D4}\u{05DE}\u{05E9}\u{05EA}\u{05EA}\u{05E3}") === 0 ||
                strpos($label, 'Participant') === 0
            ) {
                $parts = array_map('trim', explode('|', $meta->value));
                $data = array_combine($summaryKeys, array_pad($parts, count($summaryKeys), ''));
                if ($data !== false) {
                    $fallback[] = $this->normalizeTicketMeta($data);
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string,string> $data
     */
    private function normalizeTicketMeta(array $data): array
    {
        $type = isset($data['ticket_type']) ? sanitize_key((string) $data['ticket_type']) : '';
        $label = isset($data['ticket_type_label']) ? trim(wp_strip_all_tags((string) $data['ticket_type_label'])) : '';

        return [
            'ticket_type'       => $type,
            'ticket_type_label' => $label,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    private function indexTicketTypes(array $ticketTypes): array
    {
        $index = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type)) {
                continue;
            }
            $id = isset($type['id']) ? (string) $type['id'] : '';
            if ($id === '') {
                continue;
            }
            $index[$id] = [
                'name' => isset($type['name']) ? (string) $type['name'] : $id,
            ];
        }
        return $index;
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array{id:string,label:string}
     */
    private function resolveRegularTicket(array $ticketTypes): array
    {
        foreach ($ticketTypes as $type) {
            if (isset($type['id']) && (string) $type['id'] === 'general') {
                return [
                    'id'    => 'general',
                    'label' => isset($type['name']) ? (string) $type['name'] : 'general',
                ];
            }
        }

        if ($ticketTypes !== []) {
            $first = $ticketTypes[0];
            return [
                'id'    => isset($first['id']) ? (string) $first['id'] : '',
                'label' => isset($first['name']) ? (string) $first['name'] : '',
            ];
        }

        return ['id' => '', 'label' => ''];
    }

    private function resolveTicketTypeId(string $typeId, string $label, array $ticketIndex): string
    {
        if ($typeId !== '' && isset($ticketIndex[$typeId])) {
            return $typeId;
        }
        if ($label !== '') {
            foreach ($ticketIndex as $id => $meta) {
                $name = isset($meta['name']) ? (string) $meta['name'] : '';
                if ($name !== '' && AttendeeFields::labelsEqual($name, $label)) {
                    return (string) $id;
                }
            }
        }
        return $typeId;
    }

    private function isRegularTicket(string $typeId, string $label, string $regularTypeId, string $regularLabel): bool
    {
        if ($regularTypeId !== '' && $typeId === $regularTypeId) {
            return true;
        }
        if ($regularLabel !== '' && $label !== '' && AttendeeFields::labelsEqual($regularLabel, $label)) {
            return true;
        }
        if ($regularTypeId === '' && $regularLabel === '') {
            return true;
        }
        return false;
    }

    private function incrementWindowBuckets(array &$windows, int $orderTs, bool $wasReferred): void
    {
        if ($orderTs <= 0 || !is_array($windows)) {
            return;
        }
        foreach ($windows as &$window) {
            if (!is_array($window)) {
                continue;
            }
            $start = (int) ($window['start'] ?? 0);
            $end = (int) ($window['end'] ?? 0);
            if ($this->timestampInWindow($orderTs, $start, $end)) {
                $key = $wasReferred ? 'affiliate' : 'direct';
                $window[$key] = (int) ($window[$key] ?? 0) + 1;
                break;
            }
        }
        unset($window);
    }

    private function timestampInWindow(int $ts, int $start, int $end): bool
    {
        if ($ts <= 0) {
            return false;
        }
        if ($start > 0 && $ts < $start) {
            return false;
        }
        if ($end > 0 && $ts > $end) {
            return false;
        }
        return true;
    }
}
