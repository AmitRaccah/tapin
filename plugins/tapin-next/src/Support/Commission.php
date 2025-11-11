<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

final class Commission
{
    /**
     * @param array{type?:string,amount?:float} $meta
     */
    public static function calculate(array $meta, float $lineTotal, int $quantity): float
    {
        $type = isset($meta['type']) ? (string) $meta['type'] : '';
        $amount = isset($meta['amount']) ? (float) $meta['amount'] : 0.0;

        if ($amount <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            return $lineTotal > 0 ? ($lineTotal * $amount) / 100 : 0.0;
        }

        if ($type === 'flat') {
            return $quantity > 0 ? $amount * $quantity : 0.0;
        }

        return 0.0;
    }
}
