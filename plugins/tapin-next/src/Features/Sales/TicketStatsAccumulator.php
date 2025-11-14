<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
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
            $order = $item->get_order();
            if ($order instanceof WC_Order && $order->has_status(PartiallyApprovedStatus::STATUS_SLUG)) {
                $count = $this->approvedCountForItem($order, (int) $item->get_id());
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
        $itemId = (int) $item->get_id();
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded !== []) {
            $tickets = array_map([$this, 'normalizeTicketMeta'], $decoded);
            return $this->filterTicketsByApproval($tickets, $orderObj, $itemId);
        }

        $legacy = (string) $item->get_meta('Tapin Attendees', true);
        if ($legacy !== '') {
            $legacyDecoded = AttendeeSecureStorage::decrypt($legacy);
            if ($legacyDecoded !== []) {
                $tickets = array_map([$this, 'normalizeTicketMeta'], $legacyDecoded);
                return $this->filterTicketsByApproval($tickets, $orderObj, $itemId);
            }
        }

        if ($orderObj instanceof WC_Order) {
            $aggregate = $orderObj->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                $tickets = array_map([$this, 'normalizeTicketMeta'], $aggregateDecoded);
                return $this->filterTicketsByApproval($tickets, $orderObj, $itemId);
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

        return $this->filterTicketsByApproval($fallback, $orderObj, $itemId);
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
    private function filterTicketsByApproval(array $tickets, ?WC_Order $order, int $itemId): array
    {
        if (!$order instanceof WC_Order || !$order->has_status(PartiallyApprovedStatus::STATUS_SLUG)) {
            return $tickets;
        }

        $indices = $this->approvedIndicesForItem($order, $itemId);
        if ($indices !== []) {
            $filtered = [];
            foreach ($indices as $index) {
                if (isset($tickets[$index])) {
                    $filtered[] = $tickets[$index];
                }
            }
            return $filtered;
        }

        $approvedCount = $this->approvedCountForItem($order, $itemId);
        if ($approvedCount > 0 && $tickets !== []) {
            return array_slice($tickets, 0, $approvedCount);
        }

        return [];
    }

    /**
     * @return array<int,int>
     */
    private function approvedIndicesForItem(WC_Order $order, int $itemId): array
    {
        $raw = $order->get_meta('_tapin_producer_approved_attendees', true);
        if (!is_array($raw)) {
            return [];
        }

        $indices = null;
        foreach ($raw as $key => $value) {
            if ((int) $key === $itemId) {
                $indices = is_array($value) ? $value : null;
                break;
            }
        }
        if (!is_array($indices)) {
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

    private function approvedCountForItem(WC_Order $order, int $itemId): int
    {
        $raw = $order->get_meta('_tapin_partial_approved_map', true);
        if (!is_array($raw)) {
            return 0;
        }

        foreach ($raw as $key => $value) {
            if ((int) $key === $itemId) {
                return max(0, (int) $value);
            }
        }

        return 0;
    }
}
