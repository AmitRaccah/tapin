<?php
namespace Tapin\Events\Domain;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class SaleWindowsRepository {
    public static function get(int $productId): array {
        $w = get_post_meta($productId, MetaKeys::SALE_WINDOWS, true);
        $w = is_array($w) ? $w : [];
        return self::sortByStart($w);
    }

    public static function parseFromPost(string $prefix='sale_w'): array {
        $starts = isset($_POST["{$prefix}_start"]) ? (array) wp_unslash($_POST["{$prefix}_start"]) : [];
        $ends   = isset($_POST["{$prefix}_end"])   ? (array) wp_unslash($_POST["{$prefix}_end"])   : [];
        $prices = isset($_POST["{$prefix}_price"]) ? (array) wp_unslash($_POST["{$prefix}_price"]) : [];
        $out = [];
        $count = max(count($starts), count($prices));
        for ($i=0; $i<$count; $i++){
            $rawPrice = isset($prices[$i]) ? sanitize_text_field($prices[$i]) : '';
            $priceInput = str_replace(',', '.', $rawPrice);
            $price = $priceInput !== '' ? (function_exists('wc_format_decimal') ? wc_format_decimal($priceInput) : (float) $priceInput) : 0;
            $price = is_numeric($price) ? (float) $price : 0;

            $startStr = isset($starts[$i]) ? sanitize_text_field($starts[$i]) : '';
            $endStr   = isset($ends[$i])   ? sanitize_text_field($ends[$i])   : '';

            $start = $startStr !== '' ? Time::localStrToUtcTs($startStr) : 0;
            $end   = $endStr   !== '' ? Time::localStrToUtcTs($endStr)   : 0;
            if ($price>0 && $start>0 && ($end===0 || $end>$start)) {
                $out[] = ['start'=>$start,'end'=>$end,'price'=>$price];
            }
        }
        return self::sortByStart($out);
    }

    public static function save(int $productId, array $windows): void {
        $norm=[];
        foreach ($windows as $w){
            $norm[]=['start'=>(int)($w['start']??0),'end'=>(int)($w['end']??0),'price'=>(float)($w['price']??0)];
        }
        update_post_meta($productId, MetaKeys::SALE_WINDOWS, self::sortByStart($norm));
    }

    public static function findActive(int $productId): ?array {
        $now = time();
        foreach (self::get($productId) as $w){
            $s=(int)$w['start']; $e=(int)$w['end'];
            if ($s <= $now && ($e===0 || $now < $e)) return $w;
        }
        return null;
    }

    private static function sortByStart(array $windows): array {
        usort($windows, static function(array $a, array $b): int {
            return (int)($a['start'] ?? 0) <=> (int)($b['start'] ?? 0);
        });

        return array_values($windows);
    }
}
