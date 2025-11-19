<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\TicketEmails;

use Tapin\Events\Features\Orders\ProducerApprovals\OrderSummaryBuilder;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class TicketAttendeesResolver
{
    private OrderSummaryBuilder $summaryBuilder;

    public function __construct(?OrderSummaryBuilder $summaryBuilder = null)
    {
        $this->summaryBuilder = $summaryBuilder ?: new OrderSummaryBuilder();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function resolve(WC_Order $order, int $producerId): array
    {
        $summary   = $this->summaryBuilder->buildOrderSummary($order, $producerId);
        $attendees = [];

        $approvedMap      = (array) ($summary['approved_attendee_map'] ?? []);
        $hasApprovalMap   = $approvedMap !== [];

        $primary = isset($summary['primary_attendee']) && is_array($summary['primary_attendee'])
            ? (array) $summary['primary_attendee']
            : [];

        if ($primary !== []) {
            $attendees[] = $primary;
        }

        foreach ((array) ($summary['attendees'] ?? []) as $attendee) {
            if (is_array($attendee) && $attendee !== []) {
                $attendees[] = $attendee;
            }
        }

        if ($attendees === []) {
            return [];
        }

        $itemsIndex = [];
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $itemsIndex[$item->get_id()] = $item;
            }
        }

        $records = [];

        foreach ($attendees as $attendee) {
            if ($hasApprovalMap) {
                $isApproved = !empty($attendee['is_producer_approved']);
                if (!$isApproved) {
                    continue;
                }
            }

            $email = sanitize_email((string) ($attendee['email'] ?? ''));
            if ($email === '' || strpos($email, '@') === false) {
                continue;
            }

            $itemId        = isset($attendee['item_id']) ? (int) $attendee['item_id'] : 0;
            $attendeeIndex = isset($attendee['attendee_index']) ? (int) $attendee['attendee_index'] : -1;

            if ($itemId <= 0 || $attendeeIndex < 0) {
                continue;
            }

            $item = $itemsIndex[$itemId] ?? null;
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $fullName   = $this->resolveFullName($attendee);
            $productId  = (int) $item->get_product_id();
            $product    = $item->get_product();
            $productName = $product instanceof WC_Product ? $product->get_name() : $item->get_name();

            $records[] = [
                'order_id'       => (int) $order->get_id(),
                'line_item_id'   => $itemId,
                'attendee_index' => $attendeeIndex,
                'email'          => $email,
                'full_name'      => $fullName,
                'phone'          => (string) ($attendee['phone'] ?? ''),
                'ticket_type'    => (string) ($attendee['ticket_type'] ?? ''),
                'ticket_label'   => (string) ($attendee['ticket_type_label'] ?? $item->get_name()),
                'product_id'     => $productId,
                'product_name'   => $productName,
                'event_id'       => $this->resolveEventId($item),
                'producer_id'    => $producerId,
                'attendee'       => $attendee,
            ];
        }

        return $records;
    }

    private function resolveFullName(array $attendee): string
    {
        $full = trim((string) ($attendee['full_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }

        $first = trim((string) ($attendee['first_name'] ?? ''));
        $last  = trim((string) ($attendee['last_name'] ?? ''));

        $parts = array_filter([$first, $last]);

        $compiled = $parts !== [] ? implode(' ', $parts) : '';

        return sanitize_text_field($compiled);
    }

    private function resolveEventId(WC_Order_Item_Product $item): int
    {
        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            if ($product->is_type('variation')) {
                $parentId = $product->get_parent_id();
                if ($parentId) {
                    return (int) $parentId;
                }
            }

            return (int) $product->get_id();
        }

        return (int) $item->get_product_id();
    }
}
