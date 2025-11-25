<?php

namespace Tapin\Events\Support;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\CapacityValidator;

final class ProductAvailability
{
    /**
     * @var array<int,array{has_windows:bool,sale_state:string,has_tickets:bool,is_purchasable:bool,remaining_total:int,remaining_types:array<string,array{capacity:int,sold:int,remaining:int}>}>
     */
    private static array $cache = [];

    /**
     * Returns a quick boolean indicator describing whether the product can be purchased right now.
     */
    public static function isCurrentlyPurchasable(int $productId): bool
    {
        return self::status($productId)['is_purchasable'];
    }

    /**
     * Returns a structured snapshot of the sale state for a product.
     *
     * Sale state values:
     * - none: no sale windows were configured for the product.
     * - active: there is a sale window that is open right now.
     * - upcoming: windows exist, but none have opened yet.
     * - ended: windows exist, but all have closed.
     *
     * @return array{has_windows:bool,sale_state:string,has_tickets:bool,is_purchasable:bool}
     */
    public static function status(int $productId): array
    {
        if ($productId <= 0) {
            return [
                'has_windows'    => false,
                'sale_state'     => 'none',
                'has_tickets'    => false,
                'is_purchasable' => false,
            ];
        }

        if (isset(self::$cache[$productId])) {
            return self::$cache[$productId];
        }

        $types   = TicketTypesRepository::get($productId);
        $windows = SaleWindowsRepository::get($productId, $types);
        $summary = CapacityValidator::summarize($productId, $types);

        $saleState    = self::resolveSaleState($windows);
        $hasTickets   = self::hasAvailableTickets($summary);
        $isPurchasable = $hasTickets && $saleState !== 'upcoming';

        self::$cache[$productId] = [
            'has_windows'    => $windows !== [],
            'sale_state'     => $saleState,
            'has_tickets'    => $hasTickets,
            'is_purchasable' => $isPurchasable,
            'remaining_total'=> (int) $summary['total_remaining'],
            'remaining_types'=> $summary['types'],
        ];

        return self::$cache[$productId];
    }

    /**
     * @param array<int,array<string,mixed>> $windows
     */
    private static function resolveSaleState(array $windows): string
    {
        if ($windows === []) {
            return 'none';
        }

        $now = time();
        $hasUpcoming = false;

        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $start = isset($window['start']) ? (int) $window['start'] : 0;
            $end   = isset($window['end']) ? (int) $window['end'] : 0;

            if ($start <= $now && ($end === 0 || $now < $end)) {
                return 'active';
            }

            if ($start > $now) {
                $hasUpcoming = true;
            }
        }

        return $hasUpcoming ? 'upcoming' : 'ended';
    }

    /**
     * @param array{types: array<string,array{capacity:int,sold:int,remaining:int}>, total_capacity:int,total_sold:int,total_remaining:int, has_unlimited:bool} $summary
     */
    private static function hasAvailableTickets(array $summary): bool
    {
        if (!empty($summary['has_unlimited'])) {
            return true;
        }

        foreach ($summary['types'] as $meta) {
            $capacity  = isset($meta['capacity']) ? (int) $meta['capacity'] : 0;
            $remaining = isset($meta['remaining']) ? (int) $meta['remaining'] : 0;

            if ($capacity <= 0 || $remaining > 0) {
                return true;
            }
        }

        return false;
    }
}
