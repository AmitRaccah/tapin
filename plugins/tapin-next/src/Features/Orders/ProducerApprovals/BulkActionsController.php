<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class BulkActionsController
{
    /**
     * @param array<int,int> $relevantIds
     * @return array{notice: string}
     */
    public function handle(array $relevantIds): array
    {
        $notice = '';

        if (
            'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')
            && !empty($_POST['tapin_pa_bulk_nonce'])
            && wp_verify_nonce($_POST['tapin_pa_bulk_nonce'], 'tapin_pa_bulk')
        ) {
            $approveAll     = !empty($_POST['approve_all']);
            $cancelSelected = isset($_POST['bulk_cancel']);
            $bulkApprove    = isset($_POST['bulk_approve']);

            $selected    = array_map('absint', (array) ($_POST['order_ids'] ?? []));
            $attendeeMap = $this->sanitizeAttendeeSelection((array) ($_POST['attendee_approve'] ?? []));

            if ($approveAll) {
                $selected = $relevantIds;
            } elseif ($cancelSelected && $selected === [] && $attendeeMap !== []) {
                $selected = array_map('intval', array_keys($attendeeMap));
            }

            if ($bulkApprove && !$approveAll && !$cancelSelected && $attendeeMap === []) {
                return [
                    'notice' => sprintf(
                        '<div class="woocommerce-error" style="direction:rtl;text-align:right">%s</div>',
                        __('לא נבחרו משתתפים לאישור.', 'tapin')
                    ),
                ];
            }

            $approved = 0;
            $failed   = 0;

            if ($approveAll || $cancelSelected) {
                [$approved, $failed] = $this->handleOrderLevelActions(
                    array_unique(array_map('intval', $selected)),
                    $relevantIds,
                    $cancelSelected
                );
            } elseif ($bulkApprove) {
                [$approved, $failed] = $this->handleAttendeeApprovals($attendeeMap, $relevantIds);
            }

            if ($approved || $failed) {
                $notice = sprintf(
                    '<div class="woocommerce-message" style="direction:rtl;text-align:right">%s</div>',
                    sprintf(
                        esc_html__( 'אושרו %1$d הזמנות, נכשלו %2$d.', 'tapin' ),
                        $approved,
                        $failed
                    )
                );
            }
        }

        return ['notice' => $notice];
    }

    /**
     * @param array<int,int> $selected
     * @param array<int,int> $relevantIds
     * @return array{int,int}
     */
    private function handleOrderLevelActions(array $selected, array $relevantIds, bool $cancelSelected): array
    {
        $approved       = 0;
        $failed         = 0;
        $relevantLookup = array_fill_keys(array_map('intval', $relevantIds), true);

        foreach ($selected as $orderId) {
            if (!isset($relevantLookup[$orderId])) {
                $failed++;
                continue;
            }

            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                $failed++;
                continue;
            }

            $status = $order->get_status();
            if (!in_array($status, [AwaitingProducerStatus::STATUS_SLUG, PartiallyApprovedStatus::STATUS_SLUG], true)) {
                $failed++;
                continue;
            }

            if ($cancelSelected) {
                $order->update_status(
                    'cancelled',
                    'ההזמנה בוטלה לבקשת המפיק.'
                );
                $approved++;
                continue;
            }

            AwaitingProducerGate::captureAndApprove($order);
            $approved++;
        }

        return [$approved, $failed];
    }

    /**
     * @param array<int,array<int,array<int,int>>> $selection
     * @param array<int,int> $relevantIds
     * @return array{int,int}
     */
    private function handleAttendeeApprovals(array $selection, array $relevantIds): array
    {
        if ($selection === []) {
            return [0, 0];
        }

        $producerId = (int) get_current_user_id();
        if ($producerId <= 0) {
            return [0, count($selection)];
        }

        $approved       = 0;
        $failed         = 0;
        $relevantLookup = array_fill_keys(array_map('intval', $relevantIds), true);

        foreach ($selection as $orderId => $items) {
            if (!isset($relevantLookup[$orderId])) {
                $failed++;
                continue;
            }

            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                $failed++;
                continue;
            }

            $status = $order->get_status();
            if (!in_array($status, [AwaitingProducerStatus::STATUS_SLUG, PartiallyApprovedStatus::STATUS_SLUG], true)) {
                $failed++;
                continue;
            }

            if ($this->applyAttendeeSelection($order, $producerId, $items)) {
                $approved++;
            } else {
                $failed++;
            }
        }

        return [$approved, $failed];
    }

    /**
     * @param array<int,array<int,int>> $itemSelections
     */
    private function applyAttendeeSelection(WC_Order $order, int $producerId, array $itemSelections): bool
    {
        $producerItems = [];
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product && $this->isProducerLineItem($item, $producerId)) {
                $producerItems[(int) $item->get_id()] = $item;
            }
        }

        if ($producerItems === []) {
            return false;
        }

        $approvedMeta = $this->normalizeApprovedMeta(
            (array) $order->get_meta('_tapin_producer_approved_attendees', true)
        );
        $partialMap = $this->normalizePartialMap(
            (array) $order->get_meta('_tapin_partial_approved_map', true)
        );

        $selectionTouched = false;
        foreach ($producerItems as $itemId => $item) {
            if (!array_key_exists($itemId, $itemSelections)) {
                unset($approvedMeta[$itemId], $partialMap[$itemId]);
                continue;
            }

            $selectionTouched = true;
            $quantity         = max(0, (int) $item->get_quantity());
            $filtered         = $this->filterIndices($itemSelections[$itemId], $quantity);

            $approvedMeta[$itemId] = $filtered;
            $partialMap[$itemId]   = count($filtered);
        }

        if (!$selectionTouched) {
            return false;
        }

        $cleanApprovedMeta = [];
        $cleanPartialMap   = [];
        $partialTotal      = 0.0;
        $producerTotalQty  = 0;
        $producerApproved  = 0;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId  = (int) $item->get_id();
            $quantity = max(0, (int) $item->get_quantity());

            if (isset($approvedMeta[$itemId])) {
                $clean = $this->filterIndices($approvedMeta[$itemId], $quantity > 0 ? $quantity : PHP_INT_MAX);
                if ($clean !== []) {
                    $cleanApprovedMeta[$itemId] = $clean;
                }
            }

            if (isset($partialMap[$itemId])) {
                $count = min($quantity, max(0, (int) $partialMap[$itemId]));
                if ($count > 0) {
                    $cleanPartialMap[$itemId] = $count;
                    $unit = $quantity > 0 ? ((float) $item->get_total() / max(1, $quantity)) : (float) $item->get_total();
                    $partialTotal += $unit * $count;
                }
            }

            if ($this->isProducerLineItem($item, $producerId)) {
                $producerTotalQty += $quantity;
                if (isset($cleanPartialMap[$itemId])) {
                    $producerApproved += min($quantity, (int) $cleanPartialMap[$itemId]);
                }
            }
        }

        if ($producerTotalQty === 0) {
            return false;
        }

        $this->persistApprovalMeta($order, $cleanApprovedMeta, $cleanPartialMap, $partialTotal);

        if ($producerApproved <= 0) {
            $order->update_status(
                AwaitingProducerStatus::STATUS_SLUG,
                __('לא אושרו משתתפים', 'tapin')
            );
            $order->save();
            return true;
        }

        if ($producerApproved < $producerTotalQty) {
            $order->update_status(
                PartiallyApprovedStatus::STATUS_SLUG,
                __('אושרו חלק מהמשתתפים על ידי המפיק', 'tapin')
            );
            $order->save();
            return true;
        }

        $order->delete_meta_data('_tapin_partial_approved_map');
        $order->delete_meta_data('_tapin_partial_approved_total');
        $order->save();
        AwaitingProducerGate::captureAndApprove($order);

        return true;
    }

    /**
     * @param array<int,array<int,int>> $approvedMeta
     * @param array<int,int> $partialMap
     */
    private function persistApprovalMeta(WC_Order $order, array $approvedMeta, array $partialMap, float $partialTotal): void
    {
        if ($approvedMeta === []) {
            $order->delete_meta_data('_tapin_producer_approved_attendees');
        } else {
            $order->update_meta_data('_tapin_producer_approved_attendees', $approvedMeta);
        }

        if ($partialMap === []) {
            $order->delete_meta_data('_tapin_partial_approved_map');
            $order->delete_meta_data('_tapin_partial_approved_total');
        } else {
            $order->update_meta_data('_tapin_partial_approved_map', $partialMap);
            $order->update_meta_data('_tapin_partial_approved_total', $partialTotal);
        }
    }

    /**
     * @return array<int,array<int,array<int,int>>>
     */
    private function sanitizeAttendeeSelection(array $raw): array
    {
        $result = [];

        foreach ($raw as $orderId => $items) {
            $orderKey = (int) $orderId;
            if ($orderKey <= 0 || !is_array($items)) {
                continue;
            }

            $itemMap = [];
            foreach ($items as $itemId => $indices) {
                $itemKey = (int) $itemId;
                if ($itemKey <= 0) {
                    continue;
                }

                $values = is_array($indices) ? $indices : [$indices];
                $filtered = $this->filterIndices($values, PHP_INT_MAX);
                if ($filtered === []) {
                    continue;
                }

                $itemMap[$itemKey] = $filtered;
            }

            if ($itemMap === []) {
                continue;
            }

            $result[$orderKey] = $itemMap;
        }

        return $result;
    }

    /**
     * @param array<int,int|string> $indices
     * @return array<int,int>
     */
    private function filterIndices(array $indices, int $limit): array
    {
        $clean = [];
        foreach ($indices as $value) {
            $int = (int) $value;
            if ($int < 0) {
                continue;
            }
            $clean[] = $int;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        if ($limit > 0 && count($clean) > $limit) {
            $clean = array_slice($clean, 0, $limit);
        }

        return $clean;
    }

    /**
     * @param array<string|int,mixed> $meta
     * @return array<int,array<int,int>>
     */
    private function normalizeApprovedMeta(array $meta): array
    {
        $result = [];
        foreach ($meta as $itemId => $indices) {
            $itemKey = (int) $itemId;
            if ($itemKey <= 0 || !is_array($indices)) {
                continue;
            }
            $result[$itemKey] = $this->filterIndices($indices, PHP_INT_MAX);
        }

        return $result;
    }

    /**
     * @param array<string|int,mixed> $map
     * @return array<int,int>
     */
    private function normalizePartialMap(array $map): array
    {
        $result = [];
        foreach ($map as $itemId => $count) {
            $itemKey = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }
            $result[$itemKey] = $intCount;
        }

        return $result;
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
}

