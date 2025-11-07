<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;

final class FinalizationService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function computeApprovedMap(WC_Order $order): array
    {
        $map = [];

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId = (int) $item->get_id();
            $quantity = max(0, (int) $item->get_quantity());
            $unitTotal = $quantity > 0
                ? ((float) $item->get_total() / $quantity)
                : (float) $item->get_total();
            $unitTotal = $this->roundPrice($unitTotal);

            $approvedIndices = $this->sanitizeIndices((array) $item->get_meta('_tapin_attendees_approved', true), $quantity);
            $declinedIndices = $this->buildDeclinedIndices($item, $approvedIndices, $quantity);
            $approvedCount = count($approvedIndices);

            $map[$itemId] = [
                'approved_indices' => $approvedIndices,
                'declined_indices' => $declinedIndices,
                'approved_count'   => $approvedCount,
                'original_quantity'=> $quantity,
                'unit_total'       => $unitTotal,
                'approved_total'   => $this->roundPrice($unitTotal * $approvedCount),
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $approvedMap
     */
    public function applyApprovedToOrder(WC_Order $order, array $approvedMap): void
    {
        $lineTotals = 0.0;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId = (int) $item->get_id();
            if (!isset($approvedMap[$itemId])) {
                continue;
            }

            $data = $approvedMap[$itemId];
            $approvedCount = max(0, (int) ($data['approved_count'] ?? 0));
            $unitTotal = (float) ($data['unit_total'] ?? 0.0);
            $newTotal = $this->roundPrice($unitTotal * $approvedCount);
            $lineTotals += $newTotal;

            $item->set_quantity($approvedCount);
            $item->set_total($newTotal);
            $item->set_subtotal($newTotal);
            $item->update_meta_data('_tapin_attendees_approved', array_values($data['approved_indices'] ?? []));
            $item->update_meta_data('_tapin_attendees_declined', array_values($data['declined_indices'] ?? []));

            $originalQuantity = (int) ($data['original_quantity'] ?? $approvedCount);
            $this->scaleTaxes($item, $approvedCount, $originalQuantity);
        }

        $this->removeAdjustmentFee($order);
        $order->calculate_taxes();
        $order->calculate_totals(false);

        $currentLines = 0.0;
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $currentLines += (float) $item->get_total();
            }
        }

        $delta = $this->roundPrice($lineTotals - $currentLines);
        $threshold = pow(10, -1 * max(2, (int) wc_get_price_decimals()));
        if (abs($delta) >= $threshold) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name($this->adjustmentFeeName());
            $fee->set_amount($delta);
            $fee->set_total($delta);
            $order->add_item($fee);
            $order->calculate_totals(false);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $approvedMap
     */
    public function approvedGrandTotal(WC_Order $order, array $approvedMap): float
    {
        $sum = 0.0;
        foreach ($approvedMap as $data) {
            $sum += (float) ($data['approved_total'] ?? 0.0);
        }

        return $this->roundPrice($sum);
    }

    /**
     * @param array<int,int> $approvedIndices
     * @return array<int,int>
     */
    private function buildDeclinedIndices(WC_Order_Item_Product $item, array $approvedIndices, int $quantity): array
    {
        $declined = $this->sanitizeIndices((array) $item->get_meta('_tapin_attendees_declined', true), $quantity);
        if ($declined !== []) {
            return $declined;
        }

        if ($quantity <= 0) {
            return [];
        }

        $all = range(0, $quantity - 1);
        return array_values(array_diff($all, $approvedIndices));
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,int>
     */
    private function sanitizeIndices(array $values, int $limit): array
    {
        $indices = [];
        foreach ($values as $value) {
            $index = (int) $value;
            if ($index < 0) {
                continue;
            }
            if ($limit > 0 && $index >= $limit) {
                continue;
            }
            $indices[] = $index;
        }

        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    private function scaleTaxes(WC_Order_Item_Product $item, int $approved, int $original): void
    {
        $taxes = $item->get_taxes();
        if (!is_array($taxes) || empty($taxes['total'])) {
            if ($approved === 0) {
                $item->set_taxes(['total' => [], 'subtotal' => []]);
            }
            return;
        }

        if ($original <= 0) {
            $item->set_taxes(['total' => [], 'subtotal' => []]);
            return;
        }

        $ratio = $approved / $original;
        $ratio = $ratio <= 0 ? 0.0 : $ratio;

        $scaledTotal = [];
        foreach ($taxes['total'] as $taxId => $amount) {
            $scaledTotal[$taxId] = $this->roundPrice((float) $amount * $ratio);
        }

        $scaledSubtotal = [];
        foreach (($taxes['subtotal'] ?? []) as $taxId => $amount) {
            $scaledSubtotal[$taxId] = $this->roundPrice((float) $amount * $ratio);
        }

        $item->set_taxes([
            'total'    => $scaledTotal,
            'subtotal' => $scaledSubtotal,
        ]);
    }

    private function removeAdjustmentFee(WC_Order $order): void
    {
        foreach ($order->get_items('fee') as $fee) {
            if (!$fee instanceof WC_Order_Item_Fee) {
                continue;
            }
            if ($fee->get_name() === $this->adjustmentFeeName()) {
                $order->remove_item($fee->get_id());
            }
        }
    }

    private function adjustmentFeeName(): string
    {
        return __('Tapin Partial Adjustment', 'tapin');
    }

    private function roundPrice(float $value): float
    {
        return (float) wc_format_decimal($value, wc_get_price_decimals());
    }
}
