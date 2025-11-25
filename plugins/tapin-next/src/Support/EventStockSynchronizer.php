<?php

namespace Tapin\Events\Support;

use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\TicketSalesCounter;

final class EventStockSynchronizer
{
    /**
     * Sync WooCommerce stock fields from ticket type capacities and sales.
     *
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function syncFromTicketTypes(int $productId, array $ticketTypes = []): void
    {
        if ($productId <= 0) {
            return;
        }

        if ($ticketTypes === []) {
            $ticketTypes = TicketTypesRepository::get($productId);
        }

        $capacityTotal = 0;
        $soldTotal     = 0;
        $sales         = TicketSalesCounter::get($productId);

        foreach ($ticketTypes as $type) {
            if (!is_array($type)) {
                continue;
            }

            $id = isset($type['id']) ? (string) $type['id'] : '';
            if ($id === '') {
                continue;
            }

            $capacity = max(0, (int) ($type['capacity'] ?? 0));
            $sold     = isset($sales[$id]) ? max(0, (int) $sales[$id]) : (int) ($type['sold'] ?? 0);

            if ($capacity > 0 && $sold > $capacity) {
                $sold = $capacity;
            }

            $capacityTotal += $capacity;
            $soldTotal     += $sold;
        }

        $available   = max(0, $capacityTotal - $soldTotal);
        $manageStock = $capacityTotal > 0;
        $status      = ($manageStock && $available <= 0) ? 'outofstock' : 'instock';

        self::updateStockMeta($productId, $manageStock, $available, $status, !$manageStock);
    }

    public static function syncManualStock(int $productId, ?int $manualStock): void
    {
        if ($productId <= 0) {
            return;
        }

        if ($manualStock === null) {
            self::updateStockMeta($productId, false, null, 'instock', true);
            return;
        }

        $stock  = max(0, (int) $manualStock);
        $status = $stock <= 0 ? 'outofstock' : 'instock';

        self::updateStockMeta($productId, true, $stock, $status, false);
    }

    private static function updateStockMeta(
        int $productId,
        bool $manageStock,
        ?int $stock,
        string $status,
        bool $removeStock
    ): void {
        update_post_meta($productId, '_manage_stock', $manageStock ? 'yes' : 'no');

        if ($removeStock) {
            delete_post_meta($productId, '_stock');
        }

        if ($stock !== null) {
            update_post_meta($productId, '_stock', max(0, (int) $stock));
        }

        update_post_meta($productId, '_stock_status', $status);
    }
}
