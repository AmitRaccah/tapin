<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\TicketEmails;

use WC_Order;

final class TicketTokensRepository
{
    private const STORE_META_KEY  = '_tapin_ticket_tokens';
    private const TOKEN_META_PREF = '_tapin_ticket_token_';

    /**
     * @param array<int,array<string,mixed>> $tickets
     * @return array<string,array<string,mixed>>
     */
    public function createTokensForOrder(WC_Order $order, int $producerId, array $tickets): array
    {
        $orderId = (int) $order->get_id();
        if ($orderId <= 0 || $tickets === []) {
            return [];
        }

        $store  = $this->normalizeTokenStore($order->get_meta(self::STORE_META_KEY, true));
        $result = [];

        foreach ($tickets as $ticket) {
            $itemId        = isset($ticket['line_item_id']) ? (int) $ticket['line_item_id'] : (int) ($ticket['item_id'] ?? 0);
            $attendeeIndex = isset($ticket['attendee_index']) ? (int) $ticket['attendee_index'] : -1;

            if ($itemId <= 0 || $attendeeIndex < 0) {
                continue;
            }

            $ticketKey = $this->buildTicketKey($orderId, $producerId, $itemId, $attendeeIndex);

            if (!isset($store[$ticketKey])) {
                $store[$ticketKey] = $this->buildPayload($order, $ticket, $producerId, $itemId, $attendeeIndex);
            } else {
                $store[$ticketKey] = array_merge($store[$ticketKey], $this->buildPayload($order, $ticket, $producerId, $itemId, $attendeeIndex, $store[$ticketKey]));
            }

            $this->persistTokenPointer($order, $store[$ticketKey]);

            $result[$ticketKey] = $store[$ticketKey];
        }

        $order->update_meta_data(self::STORE_META_KEY, $store);
        $order->save();

        return $result;
    }

    public function markTicketApproved(WC_Order $order, string $ticketKey): void
    {
        $ticketKey = trim($ticketKey);
        if ($ticketKey === '') {
            return;
        }

        $store = $this->normalizeTokenStore($order->get_meta(self::STORE_META_KEY, true));
        if (!isset($store[$ticketKey])) {
            return;
        }

        $store[$ticketKey]['status']      = 'approved';
        $store[$ticketKey]['approved_at'] = current_time('mysql');

        $order->update_meta_data(self::STORE_META_KEY, $store);
        $order->save();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findTicketByToken(string $token): ?array
    {
        $token = $this->normalizeToken($token);
        if ($token === '') {
            return null;
        }

        if (!function_exists('wc_get_orders')) {
            return null;
        }

        $metaKey = self::TOKEN_META_PREF . $token;
        $queryArgs = [
            'limit'      => 1,
            'return'     => 'ids',
            'meta_query' => [
                [
                    'key'     => $metaKey,
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        if (function_exists('wc_get_order_statuses')) {
            $queryArgs['status'] = array_keys((array) wc_get_order_statuses());
        }

        $orders = wc_get_orders($queryArgs);

        $orderId = isset($orders[0]) ? (int) $orders[0] : 0;
        if (!$orderId) {
            return null;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return null;
        }

        $store = $this->normalizeTokenStore($order->get_meta(self::STORE_META_KEY, true));

        foreach ($store as $ticketKey => $ticketData) {
            if (!is_array($ticketData)) {
                continue;
            }
            $ticketToken = $this->normalizeToken((string) ($ticketData['token'] ?? ''));
            if ($ticketToken === $token) {
                return [
                    'ticket'     => $ticketData,
                    'order'      => $order,
                    'ticket_key' => (string) $ticketKey,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getTokensForOrder(WC_Order $order): array
    {
        return $this->normalizeTokenStore($order->get_meta(self::STORE_META_KEY, true));
    }

    private function normalizeTokenStore($value): array
    {
        $store = [];

        if (!is_array($value)) {
            return $store;
        }

        foreach ($value as $key => $entry) {
            if (!is_string($key) || !is_array($entry)) {
                continue;
            }

            $token = $this->normalizeToken((string) ($entry['token'] ?? ''));
            if ($token === '') {
                continue;
            }

            $entry['token']       = $token;
            $entry['status']      = $this->normalizeStatus($entry['status'] ?? '');
            $entry['approved_at'] = isset($entry['approved_at']) && is_string($entry['approved_at']) ? $entry['approved_at'] : null;
            $entry['item_id']     = isset($entry['item_id']) ? (int) $entry['item_id'] : 0;
            $entry['attendee_index'] = isset($entry['attendee_index']) ? (int) $entry['attendee_index'] : -1;

            $store[(string) $key] = $entry;
        }

        return $store;
    }

    /**
     * @param array<string,mixed> $ticket
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function buildPayload(WC_Order $order, array $ticket, int $producerId, int $itemId, int $attendeeIndex, ?array $existing = null): array
    {
        $orderId = (int) $order->get_id();
        $token   = isset($existing['token']) ? (string) $existing['token'] : $this->generateToken();

        $payload = [
            'order_id'       => $orderId,
            'producer_id'    => $producerId,
            'item_id'        => $itemId,
            'attendee_index' => $attendeeIndex,
            'email'          => sanitize_email((string) ($ticket['email'] ?? '')),
            'full_name'      => sanitize_text_field((string) ($ticket['full_name'] ?? '')),
            'phone'          => sanitize_text_field((string) ($ticket['phone'] ?? '')),
            'ticket_label'   => sanitize_text_field((string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''))),
            'ticket_type'    => sanitize_text_field((string) ($ticket['ticket_type'] ?? '')),
            'product_id'     => isset($ticket['product_id']) ? (int) $ticket['product_id'] : 0,
            'product_name'   => sanitize_text_field((string) ($ticket['product_name'] ?? '')),
            'token'          => $token,
            'status'         => isset($existing['status']) ? $this->normalizeStatus($existing['status']) : 'pending',
            'approved_at'    => isset($existing['approved_at']) && is_string($existing['approved_at']) ? $existing['approved_at'] : null,
        ];

        $payload['line_item_id'] = $itemId;

        return $payload;
    }

    private function buildTicketKey(int $orderId, int $producerId, int $itemId, int $attendeeIndex): string
    {
        return sprintf('%d:%d:%d:%d', $orderId, $producerId, $itemId, $attendeeIndex);
    }

    private function normalizeStatus($status): string
    {
        $status = is_string($status) ? strtolower($status) : '';
        return in_array($status, ['approved', 'pending'], true) ? $status : 'pending';
    }

    private function generateToken(): string
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $token = wp_generate_password(32, false, false);
        }

        if (!is_string($token) || $token === '') {
            $token = md5(uniqid('', true));
        }

        return $this->normalizeToken($token);
    }

    private function normalizeToken(string $token): string
    {
        $token = strtolower(trim($token));
        $token = preg_replace('/[^a-z0-9]/', '', $token);
        return is_string($token) ? $token : '';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function persistTokenPointer(WC_Order $order, array $payload): void
    {
        $token = $this->normalizeToken((string) ($payload['token'] ?? ''));
        if ($token === '') {
            return;
        }

        $order->update_meta_data(self::TOKEN_META_PREF . $token, $this->buildTicketKey(
            (int) ($payload['order_id'] ?? 0),
            (int) ($payload['producer_id'] ?? 0),
            (int) ($payload['item_id'] ?? 0),
            (int) ($payload['attendee_index'] ?? 0)
        ));
    }
}
