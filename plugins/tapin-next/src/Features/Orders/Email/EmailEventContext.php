<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class EmailEventContext
{
    /**
     * @param array<string,mixed> $ticket
     * @return array{event_name:string,event_date_label:string,event_address:string,event_city:string,ticket_label:string}
     */
    public static function fromOrder(WC_Order $order, array $ticket = [], ?int $producerId = null): array
    {
        $producerId = $producerId !== null ? (int) $producerId : null;
        $ticket     = is_array($ticket) ? $ticket : [];

        $item = self::resolveRelevantItem($order, $ticket, $producerId);
        if (!$item instanceof WC_Order_Item_Product) {
            $ticketLabel = sanitize_text_field((string) ($ticket['ticket_label'] ?? ''));
            $fallbackName = sanitize_text_field((string) ($ticket['product_name'] ?? ''));
            if ($ticketLabel === '' && $fallbackName !== '') {
                $ticketLabel = $fallbackName;
            }

            return [
                'event_name'       => $fallbackName,
                'event_date_label' => '',
                'event_address'    => '',
                'event_city'       => '',
                'ticket_label'     => $ticketLabel,
            ];
        }

        $product   = $item->get_product();
        $productId = self::resolveProductId($item);

        $eventName = $product instanceof WC_Product ? $product->get_name() : $item->get_name();
        if ($eventName === '') {
            $eventName = $item->get_name();
        }

        $eventDateTs    = $productId > 0 ? Time::productEventTs($productId) : 0;
        $eventDateLabel = $eventDateTs > 0 ? wp_strip_all_tags(Time::fmtLocal($eventDateTs)) : '';
        $address        = $productId > 0 ? (string) get_post_meta($productId, MetaKeys::EVENT_ADDRESS, true) : '';
        $city           = $productId > 0 ? (string) get_post_meta($productId, MetaKeys::EVENT_CITY, true) : '';

        $ticketLabel = isset($ticket['ticket_label']) ? (string) $ticket['ticket_label'] : '';
        if ($ticketLabel === '') {
            $ticketLabel = $item->get_name();
        }

        return [
            'event_name'       => sanitize_text_field($eventName),
            'event_date_label' => sanitize_text_field($eventDateLabel),
            'event_address'    => sanitize_text_field($address),
            'event_city'       => sanitize_text_field($city),
            'ticket_label'     => sanitize_text_field($ticketLabel),
        ];
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private static function resolveRelevantItem(WC_Order $order, array $ticket, ?int $producerId): ?WC_Order_Item_Product
    {
        $itemId = isset($ticket['order_item_id']) ? (int) $ticket['order_item_id'] : 0;
        if ($itemId <= 0 && isset($ticket['line_item_id'])) {
            $itemId = (int) $ticket['line_item_id'];
        }
        if ($itemId <= 0 && isset($ticket['item_id'])) {
            $itemId = (int) $ticket['item_id'];
        }

        $ticketProductId = isset($ticket['product_id']) ? (int) $ticket['product_id'] : 0;

        foreach ($order->get_items('line_item') as $lineItem) {
            if (!$lineItem instanceof WC_Order_Item_Product) {
                continue;
            }

            if ($itemId > 0 && (int) $lineItem->get_id() === $itemId) {
                return $lineItem;
            }

            if ($ticketProductId > 0) {
                $resolved = self::resolveProductId($lineItem);
                if ($resolved === $ticketProductId) {
                    return $lineItem;
                }
            }

            if ($producerId !== null && $producerId > 0 && self::isProducerLineItem($lineItem, $producerId)) {
                return $lineItem;
            }
        }

        return self::firstProductItem($order);
    }

    private static function firstProductItem(WC_Order $order): ?WC_Order_Item_Product
    {
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                return $item;
            }
        }

        return null;
    }

    private static function resolveProductId(WC_Order_Item_Product $item): int
    {
        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            if ($product->is_type('variation') && $product->get_parent_id()) {
                return (int) $product->get_parent_id();
            }

            return (int) $product->get_id();
        }

        return (int) $item->get_product_id();
    }

    private static function isProducerLineItem(WC_Order_Item_Product $item, int $producerId): bool
    {
        $productId = $item->get_product_id();
        if ($productId) {
            $author = (int) get_post_field('post_author', $productId);
            if ($author === $producerId) {
                return true;
            }
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                $author = (int) get_post_field('post_author', $parentId);
                if ($author === $producerId) {
                    return true;
                }
            }
        }

        return false;
    }
}
