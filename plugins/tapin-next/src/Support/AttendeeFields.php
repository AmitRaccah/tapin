<?php

namespace Tapin\Events\Support;

final class AttendeeFields
{
    /**
     * Front-facing field definitions used for rendering and validation.
     *
     * @var array<string,array<string,string>>
     */
    private const DEFINITIONS = [
        'email'      => [
            'label' => '&#1488;&#1497;&#1502;&#1497;&#1500;',
            'type'  => 'email',
        ],
        'instagram'  => [
            'label' => '&#1488;&#1497;&#1504;&#1505;&#1496;&#1490;&#1512;&#1501;',
            'type'  => 'text',
        ],
        'facebook'   => [
            'label' => '&#1508;&#1497;&#1495;&#1505;&#1489;&#1493;&#1511;',
            'type'  => 'text',
        ],
        'full_name'  => [
            'label' => '&#1513;&#1501;&#32;&#1502;&#1500;&#1488;',
            'type'  => 'text',
        ],
        'birth_date' => [
            'label' => '&#1514;&#1488;&#1512;&#1497;&#1498;&#32;&#1500;&#1491;&#1492;',
            'type'  => 'date',
        ],
        'phone'      => [
            'label' => '&#1502;&#1505;&#1508;&#1512;&#32;&#1496;&#1500;&#1508;&#1493;&#1503;',
            'type'  => 'text',
        ],
        'id_number'  => [
            'label' => '&#1514;&#1506;&#1493;&#1491;&#1514;&#32;&#1494;&#1492;&#1493;&#1514;',
            'type'  => 'text',
        ],
    ];

    /**
     * Order used when storing attendee summaries as a single string.
     *
     * @var array<int,string>
     */
    private const SUMMARY_KEYS = [
        'full_name',
        'email',
        'instagram',
        'facebook',
        'birth_date',
        'phone',
        'id_number',
    ];

    /**
     * Preferred meta keys for automatic prefill by field.
     *
     * @var array<string,array<int,string>>
     */
    private const PREFILL_META = [
        'instagram'  => ['producer_instagram', 'instagram', 'instagram_url'],
        'facebook'   => ['producer_facebook', 'facebook', 'facebook_url'],
        'phone'      => ['producer_phone_public', 'producer_phone_private', 'phone_whatsapp', 'billing_phone'],
        'birth_date' => ['birth_date', 'um_birth_date', 'um_birthdate', 'date_of_birth', 'birthdate'],
        'id_number'  => ['id_number', 'national_id'],
    ];

    public static function definitions(): array
    {
        $definitions = self::DEFINITIONS;

        foreach ($definitions as &$definition) {
            if (isset($definition['label'])) {
                $definition['label'] = self::decodeEntities($definition['label']);
            }
        }

        unset($definition);

        return $definitions;
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::definitions() as $key => $definition) {
            $labels[$key] = $definition['label'];
        }

        return $labels;
    }

    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function summaryKeys(): array
    {
        return self::SUMMARY_KEYS;
    }

    public static function prefillMeta(): array
    {
        return self::PREFILL_META;
    }

    public static function normalizeBirthDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{2})[\/.\-](\d{2})[\/.\-](\d{4})$/', $value, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return gmdate('Y-m-d', $timestamp);
        }

        return '';
    }

    public static function sanitizeValue(string $key, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        switch ($key) {
            case 'email':
                return sanitize_email($value);

            case 'phone':
            case 'id_number':
                $value = preg_replace('/\s+/', '', $value);
                return sanitize_text_field((string) $value);

            case 'birth_date':
                return self::normalizeBirthDate($value);

            default:
                return sanitize_text_field($value);
        }
    }

    private static function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
