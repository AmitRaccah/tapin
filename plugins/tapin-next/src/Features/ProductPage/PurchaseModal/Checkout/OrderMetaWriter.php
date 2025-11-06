<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use WC_Order;
use WC_Order_Item;

final class OrderMetaWriter
{
    /**
     * @param array<int,string> $hiddenKeys
     * @return array<int,string>
     */
    public function hideOrderItemMeta(array $hiddenKeys): array
    {
        $hiddenKeys[] = '_tapin_attendees_json';
        $hiddenKeys[] = '_tapin_attendees_key';
        $hiddenKeys[] = 'Tapin Attendees';
        return array_values(array_unique($hiddenKeys));
    }

    /**
     * @param array<int,\stdClass> $metaData
     * @param \WC_Order_Item $item
     * @return array<int,\stdClass>
     */
    public function filterFormattedMeta(array $metaData, $item): array
    {
        foreach ($metaData as $index => $meta) {
            if (!isset($meta->key)) {
                continue;
            }

            if (
                $meta->key === '_tapin_attendees_json'
                || $meta->key === '_tapin_attendees_key'
                || $meta->key === 'Tapin Attendees'
            ) {
                unset($metaData[$index]);
            }
        }

        return array_values($metaData);
    }

    /**
     * @param WC_Order_Item $item
     * @param array<string,mixed> $values
     * @param WC_Order|\WC_Order|null $order
     */
    public function storeOrderItemMeta($item, string $cartItemKey, array $values, $order): void
    {
        if (empty($values['tapin_attendees']) || !is_array($values['tapin_attendees'])) {
            return;
        }

        $normalizedAttendees = array_map(function (array $attendee): array {
            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $clean[$key] = AttendeeFields::sanitizeValue($key, (string) ($attendee[$key] ?? ''));
            }
            return $clean;
        }, $values['tapin_attendees']);

        foreach ($values['tapin_attendees'] as $offset => $attendee) {
            if (!isset($normalizedAttendees[$offset])) {
                continue;
            }
            if (isset($attendee['ticket_price'])) {
                $normalizedAttendees[$offset]['ticket_price'] = (float) $attendee['ticket_price'];
            }
        }

        if (isset($values['tapin_ticket_price'])) {
            $item->update_meta_data('_tapin_ticket_price', (float) $values['tapin_ticket_price']);
        }

        $encryptedAttendees = AttendeeSecureStorage::encryptAttendees($normalizedAttendees);
        if ($encryptedAttendees !== '') {
            $item->update_meta_data('_tapin_attendees_json', $encryptedAttendees);
        }

        if (!empty($values['tapin_attendees_key'])) {
            $item->update_meta_data('_tapin_attendees_key', sanitize_text_field((string) $values['tapin_attendees_key']));
        }

        $item->delete_meta_data('Tapin Attendees');

        $maskedAttendees = AttendeeSecureStorage::maskAttendees($normalizedAttendees);

        foreach ($maskedAttendees as $index => $attendee) {
            $label = sprintf(__('משתתף %d', 'tapin'), $index + 1);
            $parts = [];
            foreach (AttendeeFields::summaryKeys() as $key) {
                $parts[] = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            }
            $item->update_meta_data($label, implode(' | ', $parts));
        }

        if ($order instanceof WC_Order) {
            $existing = AttendeeSecureStorage::upgradeAggregate($order->get_meta('_tapin_attendees', true));

            $bucketKey = $item->get_id() ?: ($values['tapin_attendees_key'] ?? $cartItemKey);
            $bucketKey = (string) $bucketKey;

            $existing['line_items'][$bucketKey] = [
                'item_id'    => (int) $item->get_id(),
                'source_key' => (string) ($values['tapin_attendees_key'] ?? $cartItemKey),
                'encrypted'  => $encryptedAttendees,
                'masked'     => $maskedAttendees,
                'count'      => count($normalizedAttendees),
                'updated'    => current_time('mysql'),
            ];

            $order->update_meta_data('_tapin_attendees', $existing);
        }
    }
}
