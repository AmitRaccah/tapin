<?php
namespace Tapin\Events\Support;

final class Time {
    public static function tz(): \DateTimeZone { return wp_timezone(); }

    public static function localStrToUtcTs(string $val): int {
        $val = trim($val);
        if ($val === '') return 0;
        try { $dt = new \DateTime($val, self::tz()); return $dt->getTimestamp(); }
        catch (\Throwable $e) { return 0; }
    }

    public static function tsToLocalInput(?int $ts): string {
        if (!$ts) return '';
        $dt = new \DateTime('@'.(int)$ts);
        $dt->setTimezone(self::tz());
        return $dt->format('Y-m-d\TH:i');
    }

    public static function fmtLocal(int $ts, string $format=''): string {
        if (!$format) $format = get_option('date_format').' H:i';
        return esc_html(wp_date($format, $ts, self::tz()));
    }

    public static function productEventTs(int $productId): int {
        $local = get_post_meta($productId, MetaKeys::EVENT_DATE, true);
        if (!$local) return 0;
        return self::localStrToUtcTs((string)$local);
    }
}
