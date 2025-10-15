<?php
namespace Tapin\Events\Domain;

use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class SaleWindowsRepository {
    public static function get(int $productId): array {
        $w = get_post_meta($productId, MetaKeys::SALE_WINDOWS, true);
        $w = is_array($w) ? $w : [];
        usort($w, function($a, $b) {
            $as = isset($a['start']) ? (int)$a['start'] : 0;
            $bs = isset($b['start']) ? (int)$b['start'] : 0;
            if ($as === $bs) return 0;
            return ($as < $bs) ? -1 : 1;
        });
        return $w;
    }

    public static function parseFromPost(string $prefix='sale_w'): array {
        $starts = isset($_POST["{$prefix}_start"]) ? (array)$_POST["{$prefix}_start"] : [];
        $ends   = isset($_POST["{$prefix}_end"])   ? (array)$_POST["{$prefix}_end"]   : [];
        $prices = isset($_POST["{$prefix}_price"]) ? (array)$_POST["{$prefix}_price"] : [];
        $out = [];
        $count = max(count($starts), count($prices));
        for ($i=0; $i<$count; $i++){
            $price = isset($prices[$i]) ? floatval(str_replace(',', '.', $prices[$i])) : 0;
            $start = isset($starts[$i]) ? Time::localStrToUtcTs((string)$starts[$i]) : 0;
            $end   = isset($ends[$i])   ? Time::localStrToUtcTs((string)$ends[$i])   : 0;
            if ($price>0 && $start>0 && ($end===0 || $end>$start)) {
                $out[] = ['start'=>$start,'end'=>$end,'price'=>$price];
            }
        }
        usort($out, function($a, $b) {
            $as = isset($a['start']) ? (int)$a['start'] : 0;
            $bs = isset($b['start']) ? (int)$b['start'] : 0;
            if ($as === $bs) return 0;
            return ($as < $bs) ? -1 : 1;
        });
        return $out;
    }

    public static function save(int $productId, array $windows): void {
        $norm=[];
        foreach ($windows as $w){
            $norm[]=['start'=>(int)($w['start']??0),'end'=>(int)($w['end']??0),'price'=>(float)($w['price']??0)];
        }
        usort($norm, function($a, $b) {
            $as = isset($a['start']) ? (int)$a['start'] : 0;
            $bs = isset($b['start']) ? (int)$b['start'] : 0;
            if ($as === $bs) return 0;
            return ($as < $bs) ? -1 : 1;
        });
        update_post_meta($productId, MetaKeys::SALE_WINDOWS, $norm);
    }

    public static function findActive(int $productId): ?array {
        $now = time();
        foreach (self::get($productId) as $w){
            $s=(int)$w['start']; $e=(int)$w['end'];
            if ($s <= $now && ($e===0 || $now < $e)) return $w;
        }
        return null;
    }
}
