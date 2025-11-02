<?php

namespace Tapin\Events\Support;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;

final class ProductAvailability
{
    /**
     * @var array<int,array{has_windows:bool,sale_state:string,has_tickets:bool,is_purchasable:bool}>
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

        $saleState   = self::resolveSaleState($windows);
        $hasTickets  = self::hasAvailableTickets($types);
        $isPurchasable = ($saleState === 'none' || $saleState === 'active') && $hasTickets;

        self::$cache[$productId] = [
            'has_windows'    => $windows !== [],
            'sale_state'     => $saleState,
            'has_tickets'    => $hasTickets,
            'is_purchasable' => $isPurchasable,
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
     * @param array<int,array<string,mixed>> $types
     */
    private static function hasAvailableTickets(array $types): bool
    {
        foreach ($types as $type) {
            if (!is_array($type)) {
                continue;
            }

            $capacity  = isset($type['capacity']) ? (int) $type['capacity'] : 0;
            $available = isset($type['available']) ? (int) $type['available'] : 0;

            if ($capacity <= 0) {
                return true;
            }

            if ($available > 0) {
                return true;
            }
        }

        return false;
    }
}

