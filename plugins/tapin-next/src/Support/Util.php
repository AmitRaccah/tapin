<?php
namespace Tapin\Events\Support;

final class Util {
    public static function fmtPriceVal($v){
        return function_exists('wc_format_decimal') ? wc_format_decimal($v) : floatval($v);
    }
    public static function catOptions(): array {
        $terms = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
        if (is_wp_error($terms)) return [];
        $out = [];
        foreach($terms as $t){ if($t->slug!=='pending-events') $out[$t->slug]=$t->name; }
        return $out;
    }
    public static function catSlugsToIds($slugs): array {
        $ids=[]; foreach((array)$slugs as $s){
            $term = get_term_by('slug', sanitize_title($s), 'product_cat');
            if ($term && !is_wp_error($term)) $ids[]=(int)$term->term_id;
        }
        return array_values(array_unique($ids));
    }
}
