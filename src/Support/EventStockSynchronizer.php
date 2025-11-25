<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\TicketSalesCounter;

final class EventStockSynchronizer
{
    /**
     * Sync WooCommerce stock metadata from ticket type capacity and sales counters.
     *
     * @param array<int,array<string,mixed>>|null $ticketTypes
     */
    public static function syncFromTicketTypes(int $productId, ?array $ticketTypes = null): void
    {
        if ($productId <= 0) {
            return;
        }

        $types = $ticketTypes ?? TicketTypesRepository::get($productId);
        $sales = TicketSalesCounter::get($productId);

        $capacityTotal = 0;
        $soldTotal     = 0;

        foreach ($types as $type) {
            if (!is_array($type)) {
                continue;
            }

            $capacityTotal += max(0, (int) ($type['capacity'] ?? 0));

            $typeId = isset($type['id']) ? (string) $type['id'] : '';
            if ($typeId !== '' && isset($sales[$typeId])) {
                $soldTotal += max(0, (int) $sales[$typeId]);
            }
        }

        self::applyStockMeta($productId, $capacityTotal, $soldTotal);
    }

    public static function syncManualStock(int $productId, ?int $stock): void
    {
        if ($productId <= 0) {
            return;
        }

        if ($stock === null) {
            self::setUnlimitedStock($productId);
            return;
        }

        $normalized = max(0, (int) $stock);
        self::applyStockMeta($productId, $normalized, 0);
    }

    private static function setUnlimitedStock(int $productId): void
    {
        update_post_meta($productId, '_manage_stock', 'no');
        delete_post_meta($productId, '_stock');
        update_post_meta($productId, '_stock_status', 'instock');
    }

    private static function applyStockMeta(int $productId, int $capacity, int $sold): void
    {
        if ($capacity <= 0) {
            self::setUnlimitedStock($productId);
            return;
        }

        $remaining = max(0, $capacity - max(0, $sold));

        update_post_meta($productId, '_manage_stock', 'yes');
        update_post_meta($productId, '_stock', $remaining);
        update_post_meta($productId, '_stock_status', $remaining > 0 ? 'instock' : 'outofstock');
    }
}
