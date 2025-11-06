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
        if (preg_match('#instagram\.com/([^/?#]+)#i', $value, $matches)) {
            $handle = $matches[1];
        } else {
            $handle = ltrim($value, '@/');
        }

        return $handle !== '' ? '@' . $handle : '';
    }
}

