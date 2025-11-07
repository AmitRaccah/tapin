<?php
namespace Tapin\Events\Support;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Order-related helpers shared by gating and approval flows.
 */
final class Orders {
    /**
     * @return array<int,int> Unique producer user IDs attached to product line items.
     */
    public static function collectProducerIds(WC_Order $order): array {
        $ids = [];

        /** @var WC_Order_Item_Product $item */
        foreach ($order->get_items('line_item') as $item) {
            $productId = $item->get_product_id();
            if (!$productId) {
                continue;
            }

            $author = (int) get_post_field('post_author', $productId);
            if ($author) {
                $ids[] = $author;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function isProducerLineItem(WC_Order_Item_Product $item, int $producerId): bool {
        $productId = $item->get_product_id();
        if ($productId && (int) get_post_field('post_author', $productId) === $producerId) {
            return true;
        }

        $product = $item->get_product();
        if (!$product instanceof WC_Product) {
            return false;
        }

        $parentId = $product->get_parent_id();
        if ($parentId && (int) get_post_field('post_author', $parentId) === $producerId) {
            return true;
        }

        return false;
    }

    public static function itemEventTimestamp(WC_Order_Item_Product $item): int {
        $product = $item->get_product();
        $targetId = 0;

        if ($product instanceof WC_Product) {
            if ($product->is_type('variation')) {
                $targetId = $product->get_parent_id() ?: $product->get_id();
            } else {
                $targetId = $product->get_id();
            }
        }

        if (!$targetId) {
            $targetId = (int) $item->get_product_id();
        }

        if ($targetId) {
            $ts = Time::productEventTs($targetId);
            if ($ts > 0) {
                return $ts;
            }
        }

        $fallbackId = 0;
        if ($product instanceof WC_Product) {
            $fallbackId = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        }

        if (!$fallbackId) {
            $fallbackId = (int) $item->get_product_id();
        }

        return $fallbackId ? Time::productEventTs($fallbackId) : 0;
    }
}
