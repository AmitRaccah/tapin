<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals\Utils;

final class SocialUrl
{
    public static function trimHandle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $handle = '';
        if (preg_match('~instagram\.com/([^/?#\s]+)~i', $value, $matches)) {
            $handle = $matches[1];
        } else {
            $handle = ltrim($value, '@/');
        }

        return $handle !== '' ? '@' . $handle : '';
    }

    /**
     * @return array{display: string, url: string}
     */
    public static function normalizeInstagram(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['display' => '', 'url' => ''];
        }

        $handle  = self::trimHandle($value);
        $display = $handle !== '' ? $handle : $value;

        $candidate = $value;
        if ($handle !== '') {
            $candidate = 'https://instagram.com/' . ltrim($handle, '@');
        }

        if (!preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://' . ltrim($candidate, '/');
        }

        $url = filter_var($candidate, FILTER_VALIDATE_URL) ? (string) $candidate : '';

        return [
            'display' => $display,
            'url'     => $url,
        ];
    }
}
