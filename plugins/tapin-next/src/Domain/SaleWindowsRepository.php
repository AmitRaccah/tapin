<?php
namespace Tapin\Events\Domain;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class SaleWindowsRepository {
    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function get(int $productId, array $ticketTypes = []): array {
        $stored = get_post_meta($productId, MetaKeys::SALE_WINDOWS, true);
        $stored = is_array($stored) ? $stored : [];
        $typesIndex = self::indexTicketTypes($ticketTypes);

        $windows = [];
        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $start  = (int) ($entry['start'] ?? 0);
            $end    = (int) ($entry['end'] ?? 0);
            $prices = [];

            if (isset($entry['prices']) && is_array($entry['prices'])) {
                foreach ($entry['prices'] as $typeId => $value) {
                    $prices[$typeId] = self::sanitizePrice($value);
                }
            }

            if ($prices === [] && isset($entry['price'])) {
                $legacyPrice = self::sanitizePrice($entry['price']);
                $prices      = self::spreadPrice($legacyPrice, $typesIndex);
            }

            $prices = self::ensurePricesForTypes($prices, $typesIndex);

            $windows[] = [
                'start'  => $start,
                'end'    => $end,
                'prices' => $prices,
                'price'  => self::minPrice($prices),
            ];
        }

        return self::sortByStart($windows);
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function parseFromPost(string $prefix='sale_w', array $ticketTypes = []): array {
        $starts = isset($_POST["{$prefix}_start"]) ? (array) wp_unslash($_POST["{$prefix}_start"]) : [];
        $ends   = isset($_POST["{$prefix}_end"])   ? (array) wp_unslash($_POST["{$prefix}_end"])   : [];
        $rawPrices = isset($_POST["{$prefix}_price"]) ? wp_unslash($_POST["{$prefix}_price"]) : [];

        $typesIndex = self::indexTicketTypes($ticketTypes);
        $priceMatrix = self::normalizePriceMatrix($rawPrices, array_keys($typesIndex));

        $count = max(count($starts), count($priceMatrix['__legacy'] ?? []));
        foreach ($priceMatrix as $typeId => $list) {
            if ($typeId === '__legacy') {
                continue;
            }
            $count = max($count, count($list));
        }

        $out = [];
        for ($i=0; $i<$count; $i++){
            $startStr = isset($starts[$i]) ? sanitize_text_field($starts[$i]) : '';
            $endStr   = isset($ends[$i])   ? sanitize_text_field($ends[$i])   : '';

            $start = $startStr !== '' ? Time::localStrToUtcTs($startStr) : 0;
            $end   = $endStr   !== '' ? Time::localStrToUtcTs($endStr)   : 0;

            $priceMap = [];
            foreach ($typesIndex as $typeId => $meta) {
                if (isset($priceMatrix[$typeId][$i])) {
                    $priceMap[$typeId] = self::sanitizePrice($priceMatrix[$typeId][$i]);
                }
            }

            $legacyPrice = isset($priceMatrix['__legacy'][$i]) ? self::sanitizePrice($priceMatrix['__legacy'][$i]) : null;
            if ($priceMap === [] && $legacyPrice !== null) {
                $priceMap = self::spreadPrice($legacyPrice, $typesIndex);
            }

            $priceMap = self::ensurePricesForTypes($priceMap, $typesIndex, $legacyPrice);
            $valid    = $start > 0 && ($end === 0 || $end > $start);
            $hasPrice = self::hasPositivePrice($priceMap);

            if ($valid && $hasPrice) {
                $out[] = [
                    'start'  => $start,
                    'end'    => $end,
                    'prices' => $priceMap,
                    'price'  => self::minPrice($priceMap),
                ];
            }
        }

        return self::sortByStart($out);
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function save(int $productId, array $windows, array $ticketTypes = []): void {
        $typesIndex = self::indexTicketTypes($ticketTypes);
        $normalized = [];

        foreach ($windows as $window){
            if (!is_array($window)) {
                continue;
            }

            $start  = (int) ($window['start'] ?? 0);
            $end    = (int) ($window['end'] ?? 0);
            $prices = isset($window['prices']) && is_array($window['prices']) ? $window['prices'] : [];

            foreach ($prices as $typeId => $value) {
                $prices[$typeId] = self::sanitizePrice($value);
            }

            $prices = self::ensurePricesForTypes($prices, $typesIndex);

            $normalized[] = [
                'start'  => $start,
                'end'    => $end,
                'prices' => $prices,
                'price'  => self::minPrice($prices),
            ];
        }

        update_post_meta($productId, MetaKeys::SALE_WINDOWS, self::sortByStart($normalized));
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function findActive(int $productId, array $ticketTypes = []): ?array {
        $now = time();
        foreach (self::get($productId, $ticketTypes) as $window){
            $start = (int)$window['start'];
            $end   = (int)$window['end'];
            if ($start <= $now && ($end===0 || $now < $end)) return $window;
        }
        return null;
    }

    private static function sortByStart(array $windows): array {
        usort($windows, static function(array $a, array $b): int {
            return (int)($a['start'] ?? 0) <=> (int)($b['start'] ?? 0);
        });

        return array_values($windows);
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array<string,array<string,float>>
     */
    private static function indexTicketTypes(array $ticketTypes): array
    {
        $index = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type) || empty($type['id'])) {
                continue;
            }
            $id = (string) $type['id'];
            $index[$id] = [
                'base_price' => isset($type['base_price']) && is_numeric($type['base_price'])
                    ? (float) $type['base_price']
                    : 0.0,
            ];
        }

        if ($index === []) {
            $index['general'] = ['base_price' => 0.0];
        }

        return $index;
    }

    /**
     * @param mixed $value
     */
    private static function sanitizePrice($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }
        return 0.0;
    }

    /**
     * @param array<string,float> $prices
     * @param array<string,array<string,float>> $typesIndex
     */
    private static function ensurePricesForTypes(array $prices, array $typesIndex, ?float $fallback = null): array
    {
        $normalized = [];
        foreach ($typesIndex as $typeId => $meta) {
            if (isset($prices[$typeId]) && $prices[$typeId] > 0) {
                $normalized[$typeId] = (float) $prices[$typeId];
                continue;
            }

            if ($fallback !== null && $fallback > 0) {
                $normalized[$typeId] = $fallback;
                continue;
            }

            $base = isset($meta['base_price']) ? (float) $meta['base_price'] : 0.0;
            $normalized[$typeId] = $base > 0 ? $base : 0.0;
        }

        return $normalized;
    }

    /**
     * @param array<string,array<int,float>>|mixed $raw
     * @param array<int,string> $typeIds
     * @return array<string,array<int,float>>
     */
    private static function normalizePriceMatrix($raw, array $typeIds): array
    {
        $matrix = ['__legacy' => []];
        if (!is_array($raw)) {
            return $matrix;
        }

        if (self::isAssoc($raw)) {
            foreach ($raw as $typeId => $values) {
                if (!is_array($values)) {
                    $values = [$values];
                }
                $matrix[$typeId] = [];
                foreach ($values as $value) {
                    $matrix[$typeId][] = self::sanitizePrice($value);
                }
            }
        } else {
            foreach ($raw as $value) {
                $matrix['__legacy'][] = self::sanitizePrice($value);
            }
        }

        foreach ($typeIds as $typeId) {
            if (!isset($matrix[$typeId])) {
                $matrix[$typeId] = [];
            }
        }

        return $matrix;
    }

    /**
     * @param array<string,float> $prices
     */
    private static function minPrice(array $prices): float
    {
        $filtered = array_filter($prices, static function ($value): bool {
            return is_numeric($value) && (float) $value > 0;
        });

        if ($filtered === []) {
            return 0.0;
        }

        return (float) min($filtered);
    }

    /**
     * @param array<string,float> $prices
     */
    private static function hasPositivePrice(array $prices): bool
    {
        foreach ($prices as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,array<string,float>> $typesIndex
     * @return array<string,float>
     */
    private static function spreadPrice(float $price, array $typesIndex): array
    {
        $prices = [];
        foreach ($typesIndex as $typeId => $_) {
            $prices[$typeId] = $price;
        }
        return $prices;
    }

    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
