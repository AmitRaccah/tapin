<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class TicketStatsAccumulator
{
    private const LEGACY_PRODUCER_ID = 0;

    private WindowsBuckets $windows;

    public function __construct(?WindowsBuckets $windows = null)
    {
        $this->windows = $windows ?? new WindowsBuckets();
    }

    public function accumulate(array &$eventRow, WC_Order_Item_Product $item, bool $wasReferred, int $orderTs): void
    {
        $tickets = $this->extractTicketsFromItem($item);
        if ($tickets === []) {
            $order = $item->get_order();
            if ($order instanceof WC_Order && $order->has_status(PartiallyApprovedStatus::STATUS_SLUG)) {
                $count = $this->approvedCountForItem($order, $item);
            } else {
                $count = max(1, (int) $item->get_quantity());
            }
            if ($count > 0) {
                $tickets = array_fill(0, $count, ['ticket_type' => '', 'ticket_type_label' => '']);
            }
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
                $this->windows->increment($eventRow['stats']['windows'], $orderTs, $wasReferred);
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
        $order = $item->get_order();
        $orderObj = $order instanceof WC_Order ? $order : null;
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded !== []) {
            $tickets = array_map([$this, 'normalizeTicketMeta'], $decoded);
            return $this->filterTicketsByApproval($tickets, $orderObj, $item);
        }

        $legacy = (string) $item->get_meta('Tapin Attendees', true);
        if ($legacy !== '') {
            $legacyDecoded = AttendeeSecureStorage::decrypt($legacy);
            if ($legacyDecoded !== []) {
                $tickets = array_map([$this, 'normalizeTicketMeta'], $legacyDecoded);
                return $this->filterTicketsByApproval($tickets, $orderObj, $item);
            }
        }

        if ($orderObj instanceof WC_Order) {
            $aggregate = $orderObj->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                $tickets = array_map([$this, 'normalizeTicketMeta'], $aggregateDecoded);
                return $this->filterTicketsByApproval($tickets, $orderObj, $item);
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

        return $this->filterTicketsByApproval($fallback, $orderObj, $item);
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
     * @param array<string,array<string,string>> $ticketIndex
     */
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

    /**
     * @param array<int,array<string,string>> $tickets
     * @return array<int,array<string,string>>
     */
    private function filterTicketsByApproval(array $tickets, ?WC_Order $order, WC_Order_Item_Product $item): array
    {
        if (!$order instanceof WC_Order || !$order->has_status(PartiallyApprovedStatus::STATUS_SLUG)) {
            return $tickets;
        }

        $indices = $this->approvedIndicesForItem($order, $item);
        if ($indices !== []) {
            $filtered = [];
            foreach ($indices as $index) {
                if (isset($tickets[$index])) {
                    $filtered[] = $tickets[$index];
                }
            }
            return $filtered;
        }

        $approvedCount = $this->approvedCountForItem($order, $item);
        if ($approvedCount > 0 && $tickets !== []) {
            return array_slice($tickets, 0, $approvedCount);
        }

        return [];
    }

    /**
     * @return array<int,int>
     */
    private function approvedIndicesForItem(WC_Order $order, WC_Order_Item_Product $item): array
    {
        $producerId = $this->resolveProducerIdForItem($item);
        $itemId     = (int) $item->get_id();
        $byProducer = $this->normalizeApprovedMetaByProducer($order->get_meta('_tapin_producer_approved_attendees', true), $producerId);

        $map = [];
        if ($producerId > 0 && isset($byProducer[$producerId])) {
            $map = $byProducer[$producerId];
        } elseif (isset($byProducer[self::LEGACY_PRODUCER_ID])) {
            $map = $byProducer[self::LEGACY_PRODUCER_ID];
        } elseif ($byProducer !== []) {
            $first = reset($byProducer);
            if (is_array($first)) {
                $map = $first;
            }
        }

        $indices = isset($map[$itemId]) ? (array) $map[$itemId] : [];
        if ($indices === []) {
            return [];
        }

        $unique = [];
        foreach ($indices as $index) {
            $index = (int) $index;
            if ($index >= 0 && !array_key_exists($index, $unique)) {
                $unique[$index] = $index;
            }
        }

        return array_values($unique);
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,array<int,int>>>
     */
    private function normalizeApprovedMetaByProducer($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_array($nested)) {
                        $hasNested = true;
                        break 2;
                    }
                }
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }
                $clean = [];
                foreach ($map as $itemId => $indices) {
                    $itemKey = (int) $itemId;
                    if ($itemKey <= 0 || !is_array($indices)) {
                        continue;
                    }
                    $filtered = $this->filterIndices($indices);
                    if ($filtered !== []) {
                        $clean[$itemKey] = $filtered;
                    }
                }
                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $clean = [];
        foreach ($raw as $itemId => $indices) {
            $itemKey = (int) $itemId;
            if ($itemKey <= 0 || !is_array($indices)) {
                continue;
            }
            $filtered = $this->filterIndices($indices);
            if ($filtered !== []) {
                $clean[$itemKey] = $filtered;
            }
        }

        if ($clean === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $clean];
    }

    private function approvedCountForItem(WC_Order $order, WC_Order_Item_Product $item): int
    {
        $itemId      = (int) $item->get_id();
        $producerId  = $this->resolveProducerIdForItem($item);
        $mapByProducer = $this->normalizeProducerPartialMap($order->get_meta('_tapin_partial_approved_map', true), $producerId);

        if ($itemId <= 0) {
            return 0;
        }

        if ($producerId > 0 && isset($mapByProducer[$producerId][$itemId])) {
            return max(0, (int) $mapByProducer[$producerId][$itemId]);
        }

        if (isset($mapByProducer[self::LEGACY_PRODUCER_ID][$itemId])) {
            return max(0, (int) $mapByProducer[self::LEGACY_PRODUCER_ID][$itemId]);
        }

        foreach ($mapByProducer as $map) {
            if (isset($map[$itemId])) {
                return max(0, (int) $map[$itemId]);
            }
        }

        return 0;
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,int>>
     */
    private function normalizeProducerPartialMap($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                $hasNested = true;
                break;
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }
                $clean = $this->sanitizePartialMap($map);
                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $legacy = $this->sanitizePartialMap($raw);
        if ($legacy === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $legacy];
    }

    /**
     * @param array<int,int|string|float> $map
     * @return array<int,int>
     */
    private function sanitizePartialMap(array $map): array
    {
        $clean = [];
        foreach ($map as $itemId => $count) {
            $itemKey  = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }
            $clean[$itemKey] = $intCount;
        }

        return $clean;
    }

    private function resolveProducerIdForItem(WC_Order_Item_Product $item): int
    {
        $productId = $item->get_product_id();
        if ($productId) {
            $author = (int) get_post_field('post_author', $productId);
            if ($author > 0) {
                return $author;
            }
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                $author = (int) get_post_field('post_author', $parentId);
                if ($author > 0) {
                    return $author;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<int|string,mixed> $indices
     * @return array<int,int>
     */
    private function filterIndices(array $indices): array
    {
        $clean = [];
        foreach ($indices as $value) {
            $int = (int) $value;
            if ($int < 0) {
                continue;
            }
            $clean[] = $int;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }
}
