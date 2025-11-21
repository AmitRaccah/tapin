<?php

namespace Tapin\Events\Domain;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\TicketSalesCounter;
use Tapin\Events\Support\Util;

final class TicketTypesRepository
{
    /**
     * Retrieve all ticket types configured for a product including availability metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get(int $productId): array
    {
        $stored = get_post_meta($productId, MetaKeys::TICKET_TYPES, true);
        $types  = self::sanitize(is_array($stored) ? $stored : []);

        if ($types === []) {
            $types = [self::fallbackType($productId)];
        }

        $sales = TicketSalesCounter::get($productId);

        foreach ($types as &$type) {
            $id        = $type['id'];
            $capacity  = (int) ($type['capacity'] ?? 0);
            $sold      = isset($sales[$id]) ? max(0, (int) $sales[$id]) : 0;
            $available = max(0, $capacity - $sold);

            $type['sold']      = $sold;
            $type['available'] = $available;
        }
        unset($type);

        usort($types, static function (array $a, array $b): int {
            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        return array_values($types);
    }

    /**
     * Persist ticket types for a product.
     *
     * @param array<int,array<string,mixed>> $types
     */
    public static function save(int $productId, array $types): array
    {
        $sanitized = self::sanitize($types);

        if ($sanitized === []) {
            $sanitized = [self::fallbackType($productId)];
        }

        $sales = TicketSalesCounter::get($productId);

        $allowedIds = [];
        foreach ($sanitized as $entry) {
            $allowedIds[] = $entry['id'];
        }

        // Drop sales counters for removed ticket types and clamp sold counts.
        $filteredSales = [];
        foreach ($allowedIds as $id) {
            $capacity = 0;
            foreach ($sanitized as $entry) {
                if ($entry['id'] === $id) {
                    $capacity = (int) ($entry['capacity'] ?? 0);
                    break;
                }
            }

            $sold = isset($sales[$id]) ? max(0, (int) $sales[$id]) : 0;
            if ($sold > $capacity) {
                $sold = $capacity;
            }
            $filteredSales[$id] = $sold;
        }

        update_post_meta($productId, MetaKeys::TICKET_TYPES, $sanitized);
        TicketSalesCounter::set($productId, $filteredSales);

        return $sanitized;
    }

    /**
     * @param array<int,array<string,mixed>> $types
     */
    public static function totalCapacity(array $types): int
    {
        $total = 0;
        foreach ($types as $entry) {
            $total += max(0, (int) ($entry['capacity'] ?? 0));
        }
        return $total;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function parseFromPost(string $prefix = 'ticket_type'): array
    {
        $names      = isset($_POST["{$prefix}_name"]) ? (array) wp_unslash($_POST["{$prefix}_name"]) : [];
        $ids        = isset($_POST["{$prefix}_id"]) ? (array) wp_unslash($_POST["{$prefix}_id"]) : [];
        $prices     = isset($_POST["{$prefix}_price"]) ? (array) wp_unslash($_POST["{$prefix}_price"]) : [];
        $capacities = isset($_POST["{$prefix}_capacity"]) ? (array) wp_unslash($_POST["{$prefix}_capacity"]) : [];
        $descs      = isset($_POST["{$prefix}_description"]) ? (array) wp_unslash($_POST["{$prefix}_description"]) : [];

        $count   = max(count($names), count($ids), count($prices), count($capacities));
        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = [
                'id'          => $ids[$i] ?? '',
                'name'        => $names[$i] ?? '',
                'base_price'  => $prices[$i] ?? '',
                'capacity'    => $capacities[$i] ?? '',
                'description' => $descs[$i] ?? '',
                'sort'        => $i,
            ];
        }

        return self::sanitize($entries);
    }

    /**
     * @param array<int,array<string,mixed>> $types
     * @return array<int,array<string,mixed>>
     */
    private static function sanitize(array $types): array
    {
        $clean   = [];
        $usedIds = [];
        $index   = 0;

        foreach ($types as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = sanitize_text_field($entry['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $id   = self::normalizeId($entry['id'] ?? '', $name, $usedIds);
            $sort = isset($entry['sort']) ? (int) $entry['sort'] : $index;

            $priceRaw = is_array($entry) && array_key_exists('base_price', $entry) ? $entry['base_price'] : '';
            $price    = self::normalizePrice($priceRaw);

            $capacity = (int) ($entry['capacity'] ?? 0);
            if ($capacity < 0) {
                $capacity = 0;
            }

            $description = sanitize_text_field($entry['description'] ?? '');

            $clean[] = [
                'id'          => $id,
                'name'        => $name,
                'base_price'  => $price,
                'capacity'    => $capacity,
                'description' => $description,
                'sort'        => $sort,
            ];

            $usedIds[] = $id;
            $index++;
        }

        usort($clean, static function (array $a, array $b): int {
            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        return array_values($clean);
    }

    private static function normalizeId(string $rawId, string $name, array $used): string
    {
        $candidate = sanitize_key($rawId);
        if ($candidate === '') {
            $candidate = sanitize_title($name, '', 'save');
        }
        if ($candidate === '') {
            $candidate = 'ticket_' . substr(md5($name . microtime(true)), 0, 8);
        }

        $base   = $candidate;
        $suffix = 1;
        while (in_array($candidate, $used, true)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param mixed $value
     */
    private static function normalizePrice($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        $formatted = Util::fmtPriceVal($value);
        return is_numeric($formatted) ? (float) $formatted : 0.0;
    }

    private static function fallbackType(int $productId): array
    {
        $regularPrice = get_post_meta($productId, '_regular_price', true);
        $stock        = get_post_meta($productId, '_stock', true);

        return [
            'id'          => 'general',
            'name'        => 'כרטיס רגיל',
            'base_price'  => is_numeric($regularPrice) ? (float) $regularPrice : 0.0,
            'capacity'    => is_numeric($stock) ? max(0, (int) $stock) : 0,
            'description' => '',
            'sort'        => 0,
        ];
    }
}

