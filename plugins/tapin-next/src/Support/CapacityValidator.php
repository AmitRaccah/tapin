<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

use Tapin\Events\Domain\TicketTypesRepository;

final class CapacityValidator
{
    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array{types: array<string,array{capacity:int,sold:int,remaining:int}>, total_capacity:int,total_sold:int,total_remaining:int, has_unlimited:bool}
     */
    public static function summarize(int $productId, array $ticketTypes = []): array
    {
        $types = $ticketTypes !== [] ? $ticketTypes : TicketTypesRepository::get($productId);
        $sales = TicketSalesCounter::get($productId);

        $summary = [
            'types'           => [],
            'total_capacity'  => 0,
            'total_sold'      => 0,
            'total_remaining' => 0,
            'has_unlimited'   => false,
        ];

        foreach ($types as $type) {
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

            $remaining = $capacity > 0 ? max(0, $capacity - $sold) : -1;

            $summary['types'][$id] = [
                'capacity'  => $capacity,
                'sold'      => $sold,
                'remaining' => $remaining,
            ];

            if ($capacity > 0) {
                $summary['total_capacity']  += $capacity;
                $summary['total_sold']      += $sold;
                $summary['total_remaining'] += $remaining;
            } else {
                $summary['has_unlimited'] = true;
            }
        }

        if ($summary['has_unlimited']) {
            $summary['total_remaining'] = -1;
        }

        return $summary;
    }

    /**
     * @param array<string,int> $requested
     * @param array<string,int> $alreadyRecorded
     * @param array{types: array<string,array{capacity:int,sold:int,remaining:int}>, total_capacity:int,total_sold:int,total_remaining:int, has_unlimited:bool} $summary
     * @return array{ok:bool,insufficient:array<string,array<string,int>>}
     */
    public static function canAllocate(array $requested, array $alreadyRecorded, array $summary): array
    {
        $insufficient = [];

        foreach ($requested as $typeId => $count) {
            $typeKey = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
            if ($typeKey === '') {
                continue;
            }

            $targetCount = max(0, (int) $count);
            $typeMeta    = $summary['types'][$typeKey] ?? ['capacity' => 0, 'sold' => 0, 'remaining' => -1];

            $baseRemaining = (int) $typeMeta['remaining'];
            $available     = $baseRemaining < 0 ? PHP_INT_MAX : $baseRemaining;
            $available    += max(0, (int) ($alreadyRecorded[$typeKey] ?? 0));

            if ($targetCount > $available) {
                $insufficient[$typeKey] = [
                    'requested' => $targetCount,
                    'available' => $available,
                    'remaining' => $baseRemaining,
                ];
            }
        }

        return [
            'ok'           => $insufficient === [],
            'insufficient' => $insufficient,
        ];
    }
}
