<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals\Utils;

final class Html
{
    public static function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function cleanText($value): string
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

