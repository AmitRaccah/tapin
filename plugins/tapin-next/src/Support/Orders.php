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

    public static function isProducerLineItem($item, int $producerId): bool
    {
        if (!$item instanceof WC_Order_Item_Product || $producerId <= 0) {
            return false;
        }

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
