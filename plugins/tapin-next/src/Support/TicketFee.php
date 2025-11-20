<?php

namespace Tapin\Events\Support;

final class TicketFee
{
    private const DEFAULT_PERCENT = 5.0;

    public static function getPercent(int $productId): float
    {
        $percent = self::DEFAULT_PERCENT;

        if ($productId > 0) {
            $raw = get_post_meta($productId, MetaKeys::TICKET_FEE_PERCENT, true);
            if ($raw !== '' && $raw !== null) {
                $candidate = (float) $raw;
                if ($candidate >= 0.0) {
                    $percent = $candidate;
                }
            }
        }

        /**
         * Filter the ticket fee percentage (e.g. 5.0 for 5%).
         *
         * @param float $percent
         * @param int   $productId
         */
        $percent = (float) apply_filters('tapin/events/ticket_fee_percent', $percent, $productId);

        if ($percent < 0.0) {
            $percent = 0.0;
        }

        return $percent;
    }

    public static function applyToPrice(float $basePrice, int $productId): float
    {
        if ($basePrice <= 0.0) {
            return $basePrice;
        }

        $percent = self::getPercent($productId);
        if ($percent <= 0.0) {
            return $basePrice;
        }

        $multiplier = 1.0 + ($percent / 100.0);
        $rawTotal   = $basePrice * $multiplier;

        if (function_exists('wc_format_decimal')) {
            $decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2;
            return (float) wc_format_decimal($rawTotal, $decimals);
        }

        return round($rawTotal, 2);
    }

    public static function getFeeAmount(float $basePrice, int $productId): float
    {
        if ($basePrice <= 0.0) {
            return 0.0;
        }

        $final = self::applyToPrice($basePrice, $productId);
        $delta = $final - $basePrice;

        if ($delta <= 0.0) {
            return 0.0;
        }

        if (function_exists('wc_format_decimal')) {
            $decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2;
            return (float) wc_format_decimal($delta, $decimals);
        }

        return round($delta, 2);
    }
}
