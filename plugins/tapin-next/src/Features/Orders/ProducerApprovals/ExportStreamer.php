<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class ExportStreamer
{
    /**
     * @param array<string,mixed> $event
     * @param array<int,array<int,string>> $rows
     */
    public function stream(array $event, array $rows): void
    {
        $filenameBase = sanitize_title($event['title'] ?? 'tapin-event');
        if ($filenameBase === '') {
            $filenameBase = 'tapin-event';
        }

        $filename = sprintf(
            '%s-%d-%s.csv',
            $filenameBase,
            (int) ($event['id'] ?? 0),
            gmdate('Ymd-His')
        );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            status_header(500);
            wp_die(esc_html__('לא ניתן ליצור את קובץ ה‑CSV.', 'tapin'));
        }

        fwrite($output, "\xEF\xBB\xBF");

        $header = [
            __('מזהה אירוע', 'tapin'),
            __('שם אירוע', 'tapin'),
            __('קישור לאירוע', 'tapin'),
            __('מספר הזמנה', 'tapin'),
            __('סטטוס הזמנה', 'tapin'),
            __('תאריך הזמנה', 'tapin'),
            __('סכום הזמנה', 'tapin'),
            __('כמות כוללת', 'tapin'),
            __('שורות הזמנה', 'tapin'),
            __('שם הלקוח', 'tapin'),
            __('אימייל הלקוח', 'tapin'),
            __('טלפון הלקוח', 'tapin'),
            __('תעודת זהות ראשית', 'tapin'),
            __('סוג משתתף', 'tapin'),
            __('שם משתתף', 'tapin'),
            __('אימייל משתתף', 'tapin'),
            __('טלפון משתתף', 'tapin'),
            __('תעודת זהות משתתף', 'tapin'),
            __('תאריך לידה', 'tapin'),
            __('מגדר', 'tapin'),
            __('אינסטגרם', 'tapin'),
            __('פייסבוק', 'tapin'),
            __('וואטסאפ', 'tapin'),
        ];

        fputcsv($output, $header);
        foreach ($rows as $row) {
            fputcsv($output, array_map([self::class, 'cleanExportValue'], $row));
        }

        fclose($output);
        exit;
    }

    private static function cleanExportValue($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $text = trim((string) $value);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text !== null ? trim($text) : '';
    }
}

