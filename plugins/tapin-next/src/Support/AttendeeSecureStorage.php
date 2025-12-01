<?php

namespace Tapin\Events\Support;

use WC_Order_Item_Product;

final class AttendeeSecureStorage
{
    private const CIPHER = 'aes-256-gcm';
    private const AGGREGATE_VERSION = 2;

    /**
     * @param array<int,array<string,string>> $attendees
     */
    public static function encryptAttendees(array $attendees): string
    {
        $json = wp_json_encode(self::prepareForStorage($attendees), JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return '';
        }

        $key = self::encryptionKey();
        if ($key === null || !self::supportsEncryption()) {
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log('[attendees] encryption unavailable; aborting storage');
            }
            return '';
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log('[attendees] invalid IV length; aborting storage');
            }
            return '';
        }

        try {
            $iv = random_bytes($ivLength);
        } catch (\Exception $e) {
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log('[attendees] IV generation failed: ' . $e->getMessage());
            }
            return '';
        }

        $tag = '';
        $ciphertext = openssl_encrypt($json, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext) || $ciphertext === '' || $tag === '') {
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log('[attendees] encryption failed');
            }
            return '';
        }

        $payload = [
            'v'   => 1,
            'alg' => self::CIPHER,
            'iv'  => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data'=> base64_encode($ciphertext),
        ];

        return wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function decrypt(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded['data'], $decoded['iv'], $decoded['tag'])) {
            $key = self::encryptionKey();
            if ($key === null || !self::supportsEncryption()) {
                return [];
            }

            $ciphertext = base64_decode((string) $decoded['data'], true);
            $iv         = base64_decode((string) $decoded['iv'], true);
            $tag        = base64_decode((string) $decoded['tag'], true);

            if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)) {
                return [];
            }

            $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
            if (!is_string($plaintext) || $plaintext === '') {
                return [];
            }

            $decoded = json_decode($plaintext, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $raw              = isset($entry[$key]) ? (string) $entry[$key] : '';
                $clean[$key] = AttendeeFields::sanitizeValue($key, $raw);
            }

            if ($clean !== []) {
                $normalized[] = $clean;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,string> $attendee
     * @return array<string,string>
     */
    public static function maskAttendee(array $attendee): array
    {
        $masked = [];

        foreach (AttendeeFields::keys() as $key) {
            $value = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            $masked[$key] = sanitize_text_field(self::maskValue($key, $value));
        }

        return $masked;
    }

    /**
     * @param array<int,array<string,string>> $attendees
     * @return array<int,array<string,string>>
     */
    public static function maskAttendees(array $attendees): array
    {
        $masked = [];
        foreach ($attendees as $attendee) {
            if (is_array($attendee)) {
                $masked[] = self::maskAttendee($attendee);
            }
        }

        return $masked;
    }

    /**
     * @param mixed $aggregate
     */
    public static function upgradeAggregate($aggregate): array
    {
        if (!is_array($aggregate)) {
            return [
                'version'    => self::AGGREGATE_VERSION,
                'line_items' => [],
            ];
        }

        if (isset($aggregate['version'], $aggregate['line_items'])) {
            $lineItems = is_array($aggregate['line_items']) ? $aggregate['line_items'] : [];
            foreach ($lineItems as $key => $entry) {
                if (!is_array($entry)) {
                    unset($lineItems[$key]);
                    continue;
                }

                if (!isset($entry['masked']) && isset($entry['encrypted'])) {
                    $decoded = self::decrypt((string) $entry['encrypted']);
                    $entry['masked'] = self::maskAttendees($decoded);
                }

                $lineItems[$key] = $entry;
            }

            return [
                'version'    => self::AGGREGATE_VERSION,
                'line_items' => $lineItems,
            ];
        }

        $lineItems = [];
        foreach ($aggregate as $key => $attendees) {
            if (!is_array($attendees)) {
                continue;
            }

            $encrypted = self::encryptAttendees($attendees);
            $lineItems[(string) $key] = [
                'item_id'   => is_numeric($key) ? (int) $key : 0,
                'source_key'=> (string) $key,
                'encrypted' => $encrypted,
                'masked'    => self::maskAttendees($attendees),
            ];
        }

        return [
            'version'    => self::AGGREGATE_VERSION,
            'line_items' => $lineItems,
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function extractFromAggregate($aggregate, WC_Order_Item_Product $item): array
    {
        if (!is_array($aggregate)) {
            return [];
        }

        if (!isset($aggregate['version'], $aggregate['line_items']) || !is_array($aggregate['line_items'])) {
            $legacy = [];
            foreach ($aggregate as $entry) {
                if (is_array($entry)) {
                    $legacy = $entry;
                    break;
                }
            }
            return self::maskAttendees($legacy);
        }

        $lineItems = $aggregate['line_items'];
        $matches   = [];
        $itemId    = (int) $item->get_id();
        $sourceKey = (string) $item->get_meta('_tapin_attendees_key', true);

        foreach ($lineItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ($itemId && isset($entry['item_id']) && (int) $entry['item_id'] === $itemId) {
                $matches[] = $entry;
                continue;
            }

            if ($sourceKey !== '' && isset($entry['source_key']) && (string) $entry['source_key'] === $sourceKey) {
                $matches[] = $entry;
            }
        }

        if ($matches === [] && $lineItems !== []) {
            $first = reset($lineItems);
            if (is_array($first)) {
                $matches[] = $first;
            }
        }

        foreach ($matches as $entry) {
            if (isset($entry['encrypted']) && is_string($entry['encrypted'])) {
                $decoded = self::decrypt($entry['encrypted']);
                if ($decoded !== []) {
                    return $decoded;
                }
            }

            if (isset($entry['masked']) && is_array($entry['masked'])) {
                return self::normalizeMasked($entry['masked']);
            }
        }

        return [];
    }

    private static function supportsEncryption(): bool
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }

    private static function encryptionKey(): ?string
    {
        $default = '';
        if (defined('TAPIN_ATTENDEE_KEY')) {
            $default = (string) constant('TAPIN_ATTENDEE_KEY');
        }

        if ($default === '' && function_exists('wp_salt')) {
            $default = wp_salt('tapin_attendees');
        }

        if ($default === '' && defined('AUTH_KEY')) {
            $default = (string) AUTH_KEY;
        }

        $keySource = apply_filters('tapin_events_attendee_encryption_key', $default);
        if (!is_string($keySource) || $keySource === '') {
            return null;
        }

        return hash('sha256', $keySource, true);
    }

    /**
     * @param array<int,array<string,string>> $attendees
     * @return array<int,array<string,string>>
     */
    private static function prepareForStorage(array $attendees): array
    {
        $prepared = [];
        foreach ($attendees as $attendee) {
            if (!is_array($attendee)) {
                continue;
            }

            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $clean[$key] = AttendeeFields::sanitizeValue($key, (string) ($attendee[$key] ?? ''));
            }
            $prepared[] = $clean;
        }

        return $prepared;
    }

    private static function maskValue(string $key, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        switch ($key) {
            case 'email':
                if (strpos($value, '@') !== false) {
                    [$local, $domain] = explode('@', $value, 2);
                    $local = (string) $local;
                    $domain = (string) $domain;
                    $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));
                    return $prefix . str_repeat('*', max(1, mb_strlen($local) - mb_strlen($prefix))) . '@' . $domain;
                }
                return self::genericMask($value);

            case 'phone':
                $digits = preg_replace('/\D+/', '', $value);
                $suffix = mb_substr($digits, -4);
                return ($suffix !== '' ? '***' . $suffix : self::genericMask($value));

            case 'id_number':
                $digits = preg_replace('/\D+/', '', $value);
                $suffix = mb_substr($digits, -3);
                return ($suffix !== '' ? '***' . $suffix : self::genericMask($value));

            case 'instagram':
                $handle = ltrim($value, '@/');
                $prefix = mb_substr($handle, 0, min(2, mb_strlen($handle)));
                return '@' . $prefix . '***';

            case 'tiktok':
                $handle = ltrim($value, '@/');
                $prefix = mb_substr($handle, 0, min(2, mb_strlen($handle)));
                return '@' . $prefix . '***';

            case 'facebook':
                $url = AttendeeFields::displayValue('facebook', $value);
                if ($url === '') {
                    return '';
                }

                $parts    = wp_parse_url($url);
                $scheme   = isset($parts['scheme']) ? $parts['scheme'] : 'https';
                $host     = isset($parts['host']) ? $parts['host'] : 'facebook.com';
                $path     = isset($parts['path']) ? $parts['path'] : '';
                $segments = array_values(array_filter(explode('/', trim((string) $path, '/'))));

                if ($segments !== []) {
                    $lastIndex = count($segments) - 1;
                    $segments[$lastIndex] = self::genericMask($segments[$lastIndex]);
                }

                $maskedPath = $segments ? '/' . implode('/', $segments) : '';
                $base       = $scheme . '://' . $host;

                return $base . $maskedPath;

            case 'birth_date':
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
                    return sprintf('%s-**-**', $matches[1]);
                }
                return self::genericMask($value);

            default:
                return sanitize_text_field($value);
        }
    }

    private static function genericMask(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        $prefix = mb_substr($value, 0, 1);
        $suffix = mb_substr($value, -1);
        return $prefix . str_repeat('*', max(1, $length - 2)) . $suffix;
    }

    /**
     * @param array<int,array<string,string>> $attendees
     * @return array<int,array<string,string>>
     */
    private static function normalizeMasked(array $attendees): array
    {
        $normalized = [];
        foreach ($attendees as $attendee) {
            if (!is_array($attendee)) {
                continue;
            }

            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $clean[$key] = isset($attendee[$key]) ? sanitize_text_field((string) $attendee[$key]) : '';
            }

            $normalized[] = $clean;
        }

        return $normalized;
    }
}
