<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Integrations\Affiliate\ReferralsRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\Time;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class OrderSummaryBuilder
{
    /**
     * @param array<int,int> $orderIds
     * @return array{orders: array<int,array<string,mixed>>, customer_stats: array<string,array<string,mixed>>}
     */
    public function summarizeOrders(array $orderIds, int $producerId): array
    {
        $orders = [];
        $customerStats = [];

        foreach ($orderIds as $orderId) {
            $order = wc_get_order((int) $orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }

            $summary = $this->buildOrderSummary($order, $producerId);
            if (empty($summary['items'])) {
                continue;
            }

            $orders[] = $summary;

            $email = isset($summary['customer']['email']) ? (string) $summary['customer']['email'] : '';
            $emailKey = strtolower(trim($email));
            if ($emailKey === '') {
                continue;
            }

            if (!isset($customerStats[$emailKey])) {
                $customerStats[$emailKey] = [
                    'name'   => (string) ($summary['customer']['name'] ?? ''),
                    'email'  => $email,
                    'total'  => 0,
                    'orders' => [],
                ];
            }

            $customerStats[$emailKey]['total'] += (int) ($summary['total_quantity'] ?? 0);
            $customerStats[$emailKey]['orders'][] = [
                'order_id' => (int) ($summary['id'] ?? 0),
                'quantity' => (int) ($summary['total_quantity'] ?? 0),
            ];
        }

        return [
            'orders'         => $orders,
            'customer_stats' => $customerStats,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildOrderSummary(WC_Order $order, int $producerId): array
    {
        $items             = [];
        $attendeesList     = [];
        $totalQuantity     = 0;
        $eventMap          = [];
        $allAttendeesList  = [];
        $primaryAttendee   = null;

        foreach ($order->get_items('line_item') as $item) {
            if (!$this->isProducerLineItem($item, $producerId)) {
                continue;
            }

            $quantity = (int) $item->get_quantity();
            $items[] = sprintf('%s &#215; %d', esc_html($item->get_name()), $quantity);
            $totalQuantity += $quantity;

            $eventMeta = $this->resolveEventMeta($item);
            $eventKey  = (string) ($eventMeta['event_id'] ?: $eventMeta['product_id'] ?: $item->get_id());

            if (!isset($eventMap[$eventKey])) {
                $eventMap[$eventKey] = array_merge($eventMeta, [
                    'quantity'  => 0,
                    'lines'     => [],
                    'attendees' => [],
                ]);
            }

            $formattedTotal = function_exists('wc_price')
                ? wc_price($item->get_total(), ['currency' => $order->get_currency()])
                : number_format((float) $item->get_total(), 2);

            $lineAttendees        = $this->extractAttendees($item);
            $ticketTypeMap        = [];
            foreach ($lineAttendees as $ticketTypeAttendee) {
                if (!is_array($ticketTypeAttendee) || empty($ticketTypeAttendee['ticket_type_label'])) {
                    continue;
                }
                $typeLabel = sanitize_text_field((string) $ticketTypeAttendee['ticket_type_label']);
                if ($typeLabel === '') {
                    continue;
                }
                $ticketTypeMap[$typeLabel] = $typeLabel;
            }
            $lineTicketTypes      = array_values($ticketTypeMap);
            $lineDisplayAttendees = [];

            $eventMap[$eventKey]['quantity'] += $quantity;
            $eventMap[$eventKey]['lines'][] = [
                'name'         => $item->get_name(),
                'quantity'     => $quantity,
                'total'        => $formattedTotal,
                'ticket_types' => $lineTicketTypes,
            ];
            foreach ($lineAttendees as $attendee) {
                $allAttendeesList[] = $attendee;
                if ($primaryAttendee === null) {
                    $primaryAttendee = $attendee;
                    continue;
                }

                $attendeesList[] = $attendee;
                $lineDisplayAttendees[] = $attendee;
            }

            if ($lineDisplayAttendees !== []) {
                $eventMap[$eventKey]['attendees'] = array_merge($eventMap[$eventKey]['attendees'], $lineDisplayAttendees);
            }
        }

        $userId = (int) $order->get_user_id();
        $profile = $userId
            ? $this->getUserProfile($userId)
            : [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'birthdate'  => '',
                'gender'     => '',
                'facebook'   => '',
                'instagram'  => '',
                'whatsapp'   => '',
            ];

        $profile['gender'] = AttendeeFields::displayValue('gender', (string) ($profile['gender'] ?? ''));

        $profileUsername = '';
        $profileUrl = '';
        if ($userId) {
            $userObject = get_userdata($userId);
            if ($userObject instanceof \WP_User) {
                $rawSlug = (string) ($userObject->user_nicename ?: $userObject->user_login);
                $slug = sanitize_title($rawSlug);
                if ($slug !== '') {
                    $profileUsername = $slug;
                    $profileUrl = home_url('/user/' . rawurlencode($slug) . '/');
                }
            }
        }

        $status = $order->get_status();
        $statusLabel = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name('wc-' . $status)
            : $status;

        if ($allAttendeesList !== []) {
            $this->logAttendeeAccess($order, $producerId, count($allAttendeesList));
        }

        $referralCache = [];
        $referrals = new ReferralsRepository();
        $wasReferred = $referrals->hasReferral((int) $order->get_id(), $producerId, $referralCache);
        $saleType = $wasReferred ? 'producer_link' : 'organic';

        return [
            'id'               => $order->get_id(),
            'number'           => $order->get_order_number(),
            'date'             => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' H:i') : '',
            'timestamp'        => $order->get_date_created() ? (int) $order->get_date_created()->getTimestamp() : 0,
            'total'            => wp_strip_all_tags($order->get_formatted_order_total()),
            'total_quantity'   => $totalQuantity,
            'items'            => $items,
            'attendees'        => $attendeesList,
            'primary_attendee' => $primaryAttendee ?: [],
            'customer'         => [
                'name'  => trim($order->get_formatted_billing_full_name()) ?: $order->get_billing_first_name() ?: ($order->get_user() ? $order->get_user()->display_name : html_entity_decode('&#1500;&#1511;&#1493;&#1495;&#32;&#1488;&#1504;&#1493;&#1504;&#1497;&#1502;&#1497;', ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'customer_profile'   => [
                'username' => $profileUsername,
                'url'      => $profileUrl,
                'user_id'  => $userId,
            ],
            'profile'             => $profile,
            'primary_id_number'   => $this->findPrimaryIdNumber($allAttendeesList),
            'status'              => $status,
            'status_label'        => $statusLabel,
            'sale_type'           => $saleType,
            'is_approved'         => (bool) $order->get_meta('_tapin_producer_approved'),
            'events'              => array_values($eventMap),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveEventMeta(WC_Order_Item_Product $item): array
    {
        $product   = $item->get_product();
        $productId = $product instanceof WC_Product ? (int) $product->get_id() : (int) $item->get_product_id();
        $eventId   = 0;
        $title     = '';

        if ($product instanceof WC_Product) {
            if ($product->is_type('variation')) {
                $eventId = $product->get_parent_id() ?: $productId;
            } else {
                $eventId = $productId;
            }
            $title = $product->get_name();
        } else {
            $eventId = $productId;
            $title   = $item->get_name();
        }

        if ($title === '') {
            $title = $item->get_name();
        }

        if ($product instanceof WC_Product && $product->is_type('variation')) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                $parent = wc_get_product($parentId);
                if ($parent instanceof WC_Product) {
                    $parentName = $parent->get_name();
                    if ($parentName !== '') {
                        $title = $parentName;
                    }
                }
            }
        }

        $targetId  = $eventId ?: $productId;
        $permalink = $targetId ? (string) get_permalink($targetId) : '';
        $image     = $targetId ? (string) get_the_post_thumbnail_url($targetId, 'medium') : '';

        if ($image === '' && $productId) {
            $image = (string) get_the_post_thumbnail_url($productId, 'medium');
        }

        if ($image === '' && function_exists('wc_placeholder_img_src')) {
            $image = (string) wc_placeholder_img_src();
        }

        $eventTimestamp = $targetId ? Time::productEventTs((int) $targetId) : 0;
        $eventDateLabel = '';
        if ($eventTimestamp > 0) {
            $eventDateLabel = wp_date(
                get_option('date_format') . ' H:i',
                $eventTimestamp,
                wp_timezone()
            );
        }

        return [
            'event_id'        => $eventId ?: $productId ?: 0,
            'product_id'      => $productId ?: 0,
            'title'           => $title,
            'permalink'       => $permalink,
            'image'           => $image,
            'event_date_ts'   => $eventTimestamp,
            'event_date_label'=> $eventDateLabel,
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractAttendees(WC_Order_Item_Product $item): array
    {
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded === []) {
            $legacy = (string) $item->get_meta('Tapin Attendees', true);
            if ($legacy !== '') {
                $decoded = AttendeeSecureStorage::decrypt($legacy);
            }
        }

        if ($decoded !== []) {
            return array_map([$this, 'normalizeAttendee'], $decoded);
        }

        $order = $item->get_order();
        if ($order instanceof WC_Order) {
            $aggregate = $order->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                return array_map([$this, 'normalizeAttendee'], $aggregateDecoded);
            }
        }

        $fallback = [];
        $summaryKeys = AttendeeFields::summaryKeys();

        foreach ($item->get_formatted_meta_data('') as $meta) {
            $label = (string) $meta->key;
            if (
                strpos($label, "\u{05D4}\u{05DE}\u{05E9}\u{05EA}\u{05EA}\u{05E3}") === 0
                || strpos($label, 'Participant') === 0
                || strpos($label, '???') === 0
            ) {
                $parts = array_map('trim', explode('|', $meta->value));
                $data  = array_combine($summaryKeys, array_pad($parts, count($summaryKeys), ''));
                if ($data !== false) {
                    $fallback[] = $this->normalizeAttendee($data);
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     */
    private function findPrimaryIdNumber(array $attendees): string
    {
        foreach ($attendees as $attendee) {
            $raw = isset($attendee['id_number']) ? (string) $attendee['id_number'] : '';
            $normalized = AttendeeFields::displayValue('id_number', $raw);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param array<string,string> $data
     */
    private function normalizeAttendee(array $data): array
    {
        $normalized = [];
        foreach (AttendeeFields::keys() as $key) {
            $raw = (string) ($data[$key] ?? '');
            $sanitized = AttendeeFields::sanitizeValue($key, $raw);
            if ($sanitized !== '') {
                $normalized[$key] = $sanitized;
                continue;
            }

            $display = AttendeeFields::displayValue($key, $raw);
            $normalized[$key] = $display !== '' ? $display : sanitize_text_field($raw);
        }
        return $normalized;
    }

    private function logAttendeeAccess(WC_Order $order, int $viewerId, int $count): void
    {
        static $auditedOrders = [];

        $orderId = (int) $order->get_id();
        if ($orderId && isset($auditedOrders[$orderId])) {
            return;
        }

        if ($orderId) {
            $auditedOrders[$orderId] = true;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $user   = get_userdata($viewerId);
        $username = $user instanceof \WP_User ? $user->user_login : 'user-' . $viewerId;
        $display  = $user instanceof \WP_User ? $user->display_name : '';
        $label    = $display !== '' ? $display : $username;

        $message = sprintf(
            'Attendee data viewed by %s (ID %d) for order #%s (%d attendees)',
            $label,
            $viewerId,
            $order->get_order_number(),
            $count
        );

        $logger->info($message, ['source' => 'tapin-attendees-audit']);

        do_action('tapin_events_attendee_audit_log', $orderId, $viewerId, $count, time());
    }

    private function isProducerLineItem($item, int $producerId): bool
    {
        if (!$item instanceof WC_Order_Item_Product) {
            return false;
        }

        $productId = $item->get_product_id();
        if (!$productId) {
            return false;
        }

        if ((int) get_post_field('post_author', $productId) === $producerId) {
            return true;
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId && (int) get_post_field('post_author', $parentId) === $producerId) {
                return true;
            }
        }

        return false;
    }

    private function getUserProfile(int $userId): array
    {
        $profile = [
            'first_name' => $this->getUserMetaMulti($userId, ['first_name', 'um_first_name']),
            'last_name'  => $this->getUserMetaMulti($userId, ['last_name', 'um_last_name']),
            'birthdate'  => $this->getUserMetaMulti($userId, ['birth_date', 'date_of_birth', 'um_birthdate', 'birthdate']),
            'gender'     => $this->getUserMetaMulti($userId, ['gender', 'um_gender', 'sex']),
            'facebook'   => $this->getUserMetaMulti($userId, ['facebook', 'facebook_url']),
            'instagram'  => $this->getUserMetaMulti($userId, ['instagram', 'instagram_url']),
            'whatsapp'   => $this->getUserMetaMulti($userId, ['whatsapp', 'whatsapp_number', 'whatsapp_phone', 'phone_whatsapp']),
        ];

        $profile['facebook']  = AttendeeFields::displayValue('facebook', $profile['facebook']);
        $profile['instagram'] = AttendeeFields::displayValue('instagram', $profile['instagram']);
        $profile['whatsapp']  = AttendeeFields::displayValue('phone', $profile['whatsapp']);
        $profile['gender']    = AttendeeFields::displayValue('gender', $profile['gender']);

        return $profile;
    }

    private function getUserMetaMulti(int $userId, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->cleanMetaValue(get_user_meta($userId, $key, true));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function cleanMetaValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        return trim(wp_strip_all_tags((string) $value));
    }
}

