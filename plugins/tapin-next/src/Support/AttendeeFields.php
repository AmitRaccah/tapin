<?php

namespace Tapin\Events\Support;

final class AttendeeFields
{
    /**
     * Front-facing field definitions used for rendering and validation.
     *
     * @var array<string,array<string,mixed>>
     */
    private const DEFINITIONS = [
        'email'      => [
            'label' => 'אימייל',
            'type'  => 'email',
        ],
        'first_name' => [
            'label' => 'שם פרטי',
            'type'  => 'text',
        ],
        'last_name'  => [
            'label' => 'שם משפחה',
            'type'  => 'text',
        ],
        'instagram'  => [
            'label' => 'אינסטגרם',
            'type'  => 'text',
        ],
        'tiktok'     => [
            'label' => 'טיקטוק',
            'type'  => 'text',
        ],
        'facebook'   => [
            'label' => 'פייסבוק',
            'type'  => 'text',
        ],
        'gender'     => [
            'label'   => 'מגדר',
            'type'    => 'choice',
            'choices' => [
                'male'   => 'זכר',
                'female' => 'נקבה',
            ],
        ],
        'birth_date' => [
            'label' => 'תאריך לידה',
            'type'  => 'date',
        ],
        'phone'      => [
            'label' => 'מספר טלפון',
            'type'  => 'text',
        ],
        'id_number'  => [
            'label' => 'תעודת זהות',
            'type'  => 'text',
        ],
    ];

    /**
     * Keys that should be stored for each attendee (includes computed values).
     *
     * @var array<int,string>
     */
    private const STORAGE_KEYS = [
        'email',
        'first_name',
        'last_name',
        'full_name',
        'instagram',
        'tiktok',
        'facebook',
        'birth_date',
        'phone',
        'id_number',
        'gender',
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
        'tiktok',
        'birth_date',
        'phone',
        'id_number',
        'gender',
    ];

    /**
     * Preferred meta keys for automatic prefill by field.
     *
     * @var array<string,array<int,string>>
     */
    private const PREFILL_META = [
        'first_name' => ['first_name', 'billing_first_name'],
        'last_name'  => ['last_name', 'billing_last_name'],
        'instagram'  => ['producer_instagram', 'instagram', 'instagram_url'],
        'tiktok'     => ['producer_tiktok', 'tiktok', 'tiktok_url'],
        'facebook'   => ['producer_facebook', 'facebook', 'facebook_url'],
        'phone'      => ['producer_phone_public', 'producer_phone_private', 'phone_whatsapp', 'billing_phone'],
        'birth_date' => ['birth_date', 'um_birth_date', 'um_birthdate', 'date_of_birth', 'birthdate'],
        'gender'     => ['gender', 'um_gender', 'sex'],
        'id_number'  => ['id_number', 'national_id'],
    ];

    private const REQUIRED_FOR = [
        'email'      => ['payer' => true, 'attendee' => true],
        'first_name' => ['payer' => true, 'attendee' => true],
        'last_name'  => ['payer' => true, 'attendee' => true],
        'instagram'  => ['payer' => true, 'attendee' => true],
        'tiktok'     => ['payer' => false, 'attendee' => false],
        'facebook'   => ['payer' => false, 'attendee' => false],
        'phone'      => ['payer' => true, 'attendee' => true],
        'birth_date' => ['payer' => true, 'attendee' => true],
        'gender'     => ['payer' => true, 'attendee' => true],
        'id_number'  => ['payer' => true, 'attendee' => true],
    ];

    public static function definitions(): array
    {
        $definitions = self::DEFINITIONS;

        foreach ($definitions as &$definition) {
            if (isset($definition['label'])) {
                $definition['label'] = self::decodeEntities($definition['label']);
            }

            if (isset($definition['choices']) && is_array($definition['choices'])) {
                foreach ($definition['choices'] as $choiceKey => $choiceLabel) {
                    $definition['choices'][$choiceKey] = self::decodeEntities((string) $choiceLabel);
                }
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
        return self::STORAGE_KEYS;
    }

    public static function summaryKeys(): array
    {
        return self::SUMMARY_KEYS;
    }

    public static function requiredFor(string $key): array
    {
        $defaults = ['payer' => true, 'attendee' => true];
        $config   = isset(self::REQUIRED_FOR[$key]) && is_array(self::REQUIRED_FOR[$key])
            ? self::REQUIRED_FOR[$key]
            : $defaults;

        return [
            'payer'    => !empty($config['payer']),
            'attendee' => !empty($config['attendee']),
        ];
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
                $sanitized = sanitize_email($value);
                if ($sanitized === '' || strpos($sanitized, '@') === false) {
                    return '';
                }
                return $sanitized;

            case 'first_name':
            case 'last_name':
                return sanitize_text_field($value);

            case 'phone':
                return self::normalizePhone($value);

            case 'id_number':
                return self::normalizeIdNumber($value);

            case 'instagram':
                return self::normalizeInstagramHandle($value);

            case 'tiktok':
                return self::normalizeTikTokHandle($value);

            case 'facebook':
                return self::normalizeFacebookUrl($value);

            case 'birth_date':
                return self::normalizeBirthDate($value);

            case 'gender':
                return self::normalizeGender($value);

            default:
                return sanitize_text_field($value);
        }
    }

    public static function displayValue(string $key, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        switch ($key) {
            case 'email':
                $email = sanitize_email($value);
                return $email !== '' ? $email : sanitize_text_field($value);

            case 'first_name':
            case 'last_name':
                return sanitize_text_field($value);

            case 'phone':
                $digits = preg_replace('/\D+/', '', $value);
                return strpos($value, '+') === 0 ? '+' . $digits : $digits;

            case 'id_number':
                $digits = preg_replace('/\D+/', '', $value);
                return $digits;

            case 'instagram':
                $handle = self::normalizeInstagramHandle($value);
                return $handle === '' ? '' : '@' . $handle;

            case 'tiktok':
                $handle = self::normalizeTikTokHandle($value);
                return $handle === '' ? '' : '@' . $handle;

            case 'facebook':
                if (stripos($value, 'facebook') === false) {
                    $handle = ltrim($value, '@/');
                    if ($handle === '' || !preg_match('/^[A-Za-z0-9._-]{1,50}$/', $handle)) {
                        return '';
                    }
                    return 'https://facebook.com/' . $handle;
                }
                if (!preg_match('#^https?://#i', $value)) {
                    $value = 'https://' . ltrim($value, '/');
                }
                $url = esc_url_raw($value, ['http', 'https']);
                if ($url !== '' && stripos($url, 'facebook') !== false) {
                    return $url;
                }
                return sanitize_text_field($value);

            case 'birth_date':
                return self::normalizeBirthDate($value) ?: sanitize_text_field($value);

            case 'gender':
                $normalized = self::normalizeGender($value);
                if ($normalized === 'male') {
                    return 'זכר';
                }
                if ($normalized === 'female') {
                    return 'נקבה';
                }
                $lower = strtolower($value);
                if (in_array($lower, ['זכר', 'male', 'איש'], true)) {
                    return 'זכר';
                }
                if (in_array($lower, ['נקבה', 'female', 'אישה', 'אשה'], true)) {
                    return 'נקבה';
                }
                return sanitize_text_field($value);

            default:
                return sanitize_text_field($value);
        }
    }

    private static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) < 10) {
            return '';
        }

        return (strpos($value, '+') === 0) ? '+' . $digits : $digits;
    }

    private static function normalizeIdNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) !== 9) {
            return '';
        }
        return $digits;
    }

    private static function normalizeGender(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $map = [
            'male'   => 'male',
            'm'      => 'male',
            'זכר'    => 'male',
            'man'    => 'male',
            'גבר'    => 'male',
            'בן'     => 'male',
            'boy'    => 'male',
            'איש'    => 'male',
            'female' => 'female',
            'f'      => 'female',
            'נקבה'   => 'female',
            'אישה'   => 'female',
            'אשה'    => 'female',
            'בת'     => 'female',
            'girl'   => 'female',
            'woman'  => 'female',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        return '';
    }

    private static function normalizeInstagramHandle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $handle = '';
        if (preg_match('#instagram\.com/(@?[^/?#]+)#i', $value, $matches)) {
            $handle = $matches[1];
        } else {
            $handle = ltrim($value, '@/');
        }

        $handle = strtolower(rtrim($handle, '/'));

        if ($handle === '' || !preg_match('/^[a-z0-9._]{1,30}$/', $handle)) {
            return '';
        }

        return $handle;
    }

    private static function normalizeTikTokHandle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $handle = '';
        if (preg_match('#tiktok\.com/@([^/?#]+)#i', $value, $matches)) {
            $handle = $matches[1];
        } else {
            $handle = ltrim($value, '@/');
        }

        $handle = strtolower(rtrim($handle, '/'));

        if ($handle === '' || !preg_match('/^[a-z0-9._]{1,24}$/', $handle)) {
            return '';
        }

        return $handle;
    }

    private static function normalizeFacebookUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'facebook') === false) {
            $handle = ltrim($value, '@/');
            if ($handle === '' || !preg_match('/^[A-Za-z0-9._-]{1,50}$/', $handle)) {
                return '';
            }
            return 'https://facebook.com/' . $handle;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        $url = esc_url_raw($value, ['http', 'https']);
        if ($url === '' || stripos($url, 'facebook') === false) {
            return '';
        }

        return $url;
    }

    private static function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
