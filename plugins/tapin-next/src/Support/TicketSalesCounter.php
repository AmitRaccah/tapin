<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

final class TicketSalesCounter
{
    /**
     * @return array<string,int>
     */
    public static function get(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $raw = get_post_meta($productId, MetaKeys::TICKET_TYPE_SALES, true);
        if (!is_array($raw)) {
            return [];
        }

        $sales = [];
        foreach ($raw as $typeId => $count) {
            $id = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
            if ($id === '') {
                continue;
            }

            $sales[$id] = max(0, (int) $count);
        }

        return $sales;
    }

    /**
     * @param array<string,int> $deltas
     * @return array<string,int> Updated sales map
     */
    public static function adjust(int $productId, array $deltas): array
    {
        if ($productId <= 0 || $deltas === []) {
            return self::get($productId);
        }

        $current = self::get($productId);

        foreach ($deltas as $typeId => $delta) {
            $id = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
            if ($id === '') {
                continue;
            }

            $existing = $current[$id] ?? 0;
            $current[$id] = max(0, $existing + (int) $delta);
        }

        $normalized = self::filterZeros($current);
        update_post_meta($productId, MetaKeys::TICKET_TYPE_SALES, $normalized);

        if (class_exists(\Tapin\Events\Support\ProductAvailability::class)) {
            \Tapin\Events\Support\ProductAvailability::reset($productId);
        }

        return $normalized;
    }

    /**
     * @param array<string,int> $counts
     */
    public static function set(int $productId, array $counts): array
    {
        if ($productId <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($counts as $typeId => $count) {
            $id = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
            if ($id === '') {
                continue;
            }

            $normalized[$id] = max(0, (int) $count);
        }

        $normalized = self::filterZeros($normalized);
        update_post_meta($productId, MetaKeys::TICKET_TYPE_SALES, $normalized);

        if (class_exists(\Tapin\Events\Support\ProductAvailability::class)) {
            \Tapin\Events\Support\ProductAvailability::reset($productId);
        }

        return $normalized;
    }

    /**
     * @param array<string,int> $sales
     * @return array<string,int>
     */
    private static function filterZeros(array $sales): array
    {
        $filtered = [];
        foreach ($sales as $typeId => $count) {
            if ($count > 0) {
                $filtered[$typeId] = $count;
            }
        }

        return $filtered;
    }
}
