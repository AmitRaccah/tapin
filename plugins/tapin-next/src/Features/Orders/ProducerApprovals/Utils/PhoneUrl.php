<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals\Utils;

final class PhoneUrl
{
    public static function digits(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw);
    }

    public static function telHref(string $raw): string
    {
        $href = preg_replace('/[^0-9+]/', '', $raw);
        return $href !== null ? $href : '';
    }

    public static function whatsappUrl(string $raw): string
    {
        $digits = self::digits($raw);
        return $digits !== '' ? ('https://wa.me/' . $digits) : '';
    }

    /**
     * @return array{digits: string, href: string}
     */
    public static function normalizePhone(string $raw): array
    {
        return [
            'digits' => self::digits($raw),
            'href'   => self::telHref($raw),
        ];
    }
}
