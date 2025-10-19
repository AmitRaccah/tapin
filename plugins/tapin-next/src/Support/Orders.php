<?php
namespace Tapin\Events\Support;

use WC_Order;
use WC_Order_Item_Product;

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
}
