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
    public static function fromOrder(WC_Order $order, array $ticket = []): array
    {
        $item = self::firstProductItem($order);
        if (!$item instanceof WC_Order_Item_Product) {
            return [
                'event_name'      => '',
                'event_date_label'=> '',
                'event_address'   => '',
                'event_city'      => '',
                'ticket_label'    => sanitize_text_field((string) ($ticket['ticket_label'] ?? '')),
            ];
        }

        $product   = $item->get_product();
        $productId = $product instanceof WC_Product ? (int) $product->get_id() : (int) $item->get_product_id();

        if ($product instanceof WC_Product && $product->is_type('variation') && $product->get_parent_id()) {
            $productId = (int) $product->get_parent_id();
        }

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

    private static function firstProductItem(WC_Order $order): ?WC_Order_Item_Product
    {
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                return $item;
            }
        }

        return null;
    }
}
