<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use WC_Order;
use WC_Order_Item_Product;

final class TicketStatsAccumulator
{
    private WindowsBuckets $windows;

    public function __construct(?WindowsBuckets $windows = null)
    {
        $this->windows = $windows ?? new WindowsBuckets();
    }

    public function accumulate(array &$eventRow, WC_Order_Item_Product $item, bool $wasReferred, int $orderTs): void
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
}
