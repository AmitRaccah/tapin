<?php
declare(strict_types=1);

namespace Tapin\Events\Support;

final class Search
{
    public static function normalize(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($text === null) {
            $text = '';
        }

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        return trim($text);
    }
}
