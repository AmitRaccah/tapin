<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Support\Orders;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class OrderQuery
{
    /**
     * @return array{relevant: array<int,int>, display: array<int,int>}
     */
    public function resolveProducerOrderIds(int $producerId): array
    {
        $pendingStatusKeys = array_values(array_unique([
            AwaitingProducerStatus::STATUS_KEY,
            PartiallyApprovedStatus::STATUS_KEY,
        ]));

        $awaitingIds = wc_get_orders([
            'status'  => $pendingStatusKeys,
            'limit'   => 200,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'ids',
        ]);

        foreach ($awaitingIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && !$order->get_meta('_tapin_producer_ids')) {
                $order->update_meta_data('_tapin_producer_ids', Orders::collectProducerIds($order));
                $order->save();
            }
        }

        $pendingIds = wc_get_orders([
            'status'  => $pendingStatusKeys,
            'limit'   => 200,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'ids',
        ]);

        $relevantIds = [];
        foreach ($pendingIds as $orderId) {
            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && $this->orderBelongsToProducer($order, $producerId)) {
                $relevantIds[] = (int) $orderId;
            }
        }

        $relevantIds = array_values(array_unique(array_map('intval', $relevantIds)));

        $displayIds = $relevantIds;

        $historyStatuses = [
            'wc-processing',
            'wc-completed',
            'wc-cancelled',
            'wc-refunded',
            'wc-failed',
        ];

        $historyIds = wc_get_orders([
            'status'  => $historyStatuses,
            'limit'   => 200,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'ids',
        ]);

        foreach ($historyIds as $orderId) {
            if (in_array($orderId, $displayIds, true)) {
                continue;
            }

            $order = wc_get_order($orderId);
            if ($order instanceof WC_Order && $this->orderBelongsToProducer($order, $producerId)) {
                $displayIds[] = (int) $orderId;
            }
        }

        $displayIds = array_values(array_unique(array_map('intval', $displayIds)));

        return [
            'relevant' => $relevantIds,
            'display'  => $displayIds,
        ];
    }

    private function isProducerLineItem($item, int $producerId): bool
    {
        if (!$item instanceof WC_Order_Item_Product) {
            return false;
        }

        $productId = $item->get_product_id();
        if (!$productId) {
            return false;
        }

        if ((int) get_post_field('post_author', $productId) === $producerId) {
            return true;
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId && (int) get_post_field('post_author', $parentId) === $producerId) {
                return true;
            }
        }

        return false;
    }

    private function orderBelongsToProducer(WC_Order $order, int $producerId): bool
    {
        $metaIds = array_filter(array_map('intval', (array) $order->get_meta('_tapin_producer_ids')));
        if ($metaIds && in_array($producerId, $metaIds, true)) {
            return true;
        }

        foreach ($order->get_items('line_item') as $item) {
            if ($this->isProducerLineItem($item, $producerId)) {
                return true;
            }
        }

        return false;
    }
}

