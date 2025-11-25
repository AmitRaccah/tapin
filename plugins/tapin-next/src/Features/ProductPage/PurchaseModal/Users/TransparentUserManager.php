<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Users;

use Tapin\Events\Features\ProductPage\PurchaseModal\Fields\FieldDefinitionsProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Validation\AttendeeSanitizer;
use Tapin\Events\Support\AttendeeFields;

final class TransparentUserManager
{
    private FieldDefinitionsProvider $fields;
    private AttendeeSanitizer $sanitizer;

    public function __construct(FieldDefinitionsProvider $fields, AttendeeSanitizer $sanitizer)
    {
        $this->fields = $fields;
        $this->sanitizer = $sanitizer;
    }

    /**
     * @param array<int,string> $allowedFields
     */
    public function getPrefillData(array $allowedFields = []): array
    {
        $definitions = $this->fields->getDefinitions();
        $fieldKeys = array_keys($definitions);
        $prefill = [];
        foreach ($fieldKeys as $key) {
            $prefill[$key] = '';
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return (array) apply_filters('tapin_purchase_modal_prefill', $prefill, 0);
        }

        $user = get_userdata($userId);
        $userFirstName = '';
        $userLastName = '';
        $displayName = '';

        if ($user) {
            if (isset($prefill['email'])) {
                $prefill['email'] = sanitize_email($user->user_email);
            }

            $userFirstName = AttendeeFields::sanitizeValue('first_name', (string) $user->first_name);
            $userLastName = AttendeeFields::sanitizeValue('last_name', (string) $user->last_name);
            $displayName = trim((string) $user->display_name);
        }

        foreach (AttendeeFields::prefillMeta() as $fieldKey => $metaKeys) {
            if (!array_key_exists($fieldKey, $prefill)) {
                continue;
            }

            foreach ($metaKeys as $metaKey) {
                $rawValue = get_user_meta($userId, $metaKey, true);
                if (is_array($rawValue) || is_object($rawValue)) {
                    continue;
                }
                $raw = (string) $rawValue;
                if ($raw === '') {
                    continue;
                }

                $sanitized = AttendeeFields::sanitizeValue($fieldKey, $raw);
                if ($sanitized === '') {
                    $display = AttendeeFields::displayValue($fieldKey, $raw);
                    if ($display === '') {
                        continue;
                    }
                    $prefill[$fieldKey] = $this->sanitizer->formatPrefillValue($fieldKey, $display);
                    break;
                }

                $prefill[$fieldKey] = $this->sanitizer->formatPrefillValue($fieldKey, $sanitized);
                break;
            }
        }

        if (isset($prefill['first_name']) && $prefill['first_name'] === '' && $userFirstName !== '') {
            $prefill['first_name'] = $this->sanitizer->formatPrefillValue('first_name', $userFirstName);
        }

        if (isset($prefill['last_name']) && $prefill['last_name'] === '' && $userLastName !== '') {
            $prefill['last_name'] = $this->sanitizer->formatPrefillValue('last_name', $userLastName);
        }

        if ($displayName !== '') {
            $needsFirst = isset($prefill['first_name']) && $prefill['first_name'] === '';
            $needsLast = isset($prefill['last_name']) && $prefill['last_name'] === '';

            if ($needsFirst || $needsLast) {
                $parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
                if (is_array($parts) && $parts !== []) {
                    $firstPart = array_shift($parts);
                    if ($needsFirst && $firstPart !== null) {
                        $prefill['first_name'] = $this->sanitizer->formatPrefillValue('first_name', (string) $firstPart);
                    }

                    if ($needsLast && $parts !== []) {
                        $prefill['last_name'] = $this->sanitizer->formatPrefillValue('last_name', implode(' ', $parts));
                    }
                }
            }
        }

        $prefill = (array) apply_filters('tapin_purchase_modal_prefill', $prefill, $userId);

        if ($allowedFields !== []) {
            $allowedKeys = array_fill_keys($allowedFields, true);
            $prefill = array_intersect_key($prefill, $allowedKeys);
        }

        return $prefill;
    }

    public function createTransparentUser(array $payer): ?int
    {
        $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
        if ($email === '') {
            wc_add_notice(__('כתובת האימייל אינה תקינה.', 'tapin'), 'error');
            return null;
        }

        $firstName = sanitize_text_field($payer['first_name'] ?? '');
        $lastName = sanitize_text_field($payer['last_name'] ?? '');
        $username = $this->generateUsername($firstName, $lastName, $email);
        $password = wp_generate_password(32, true);

        $userId = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'customer',
        ]);

        if (is_wp_error($userId)) {
            wc_add_notice(__('לא ניתן היה ליצור משתמש חדש, אנא נסו שוב או פנו לתמיכה.', 'tapin'), 'error');
            return null;
        }

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);
        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($userId);
        }

        $user = get_userdata($userId);
        if ($user && $user->user_login) {
            do_action('wp_login', $user->user_login, $user);
        }

        return (int) $userId;
    }

    public function generateUsername(string $firstName, string $lastName, string $email): string
    {
        $candidate = sanitize_user($firstName . $lastName, true);
        if ($candidate === '') {
            $parts = explode('@', $email);
            $candidate = sanitize_user($parts[0] ?? '', true);
        }
        if ($candidate === '') {
            $candidate = 'tapin_user';
        }

        $username = $candidate;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $candidate . $suffix;
            $suffix++;
        }

        return $username;
    }

    public function maybeUpdateUserProfile(int $userId, array $payer, bool $force = false): void
    {
        if ($userId <= 0) {
            return;
        }

        $fields = [
            'first_name' => sanitize_text_field($payer['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($payer['last_name'] ?? ''),
            'phone'      => AttendeeFields::sanitizeValue('phone', (string) ($payer['phone'] ?? '')),
            'instagram'  => AttendeeFields::sanitizeValue('instagram', (string) ($payer['instagram'] ?? '')),
            'tiktok'     => AttendeeFields::sanitizeValue('tiktok', (string) ($payer['tiktok'] ?? '')),
            'facebook'   => AttendeeFields::sanitizeValue('facebook', (string) ($payer['facebook'] ?? '')),
            'birth_date' => AttendeeFields::sanitizeValue('birth_date', (string) ($payer['birth_date'] ?? '')),
            'gender'     => AttendeeFields::sanitizeValue('gender', (string) ($payer['gender'] ?? '')),
            'id_number'  => AttendeeFields::sanitizeValue('id_number', (string) ($payer['id_number'] ?? '')),
        ];

        $this->maybeUpdateMeta($userId, 'first_name', $fields['first_name'], $force);
        $this->maybeUpdateMeta($userId, 'last_name', $fields['last_name'], $force);
        $this->maybeUpdateMeta($userId, 'billing_first_name', $fields['first_name'], $force);
        $this->maybeUpdateMeta($userId, 'billing_last_name', $fields['last_name'], $force);

        $phone = $fields['phone'];
        if ($phone !== '') {
            $this->maybeUpdateMeta($userId, 'billing_phone', $phone, $force);
            $this->maybeUpdateMeta($userId, 'phone_whatsapp', preg_replace('/\D+/', '', $phone), $force);
        }

        $instagramHandle = $fields['instagram'];
        if ($instagramHandle !== '') {
            $rawInstagram = (string) get_user_meta($userId, 'instagram', true);
            $currentHandle = AttendeeFields::sanitizeValue('instagram', $rawInstagram);
            if ($force || $currentHandle === '' || $currentHandle !== $instagramHandle || trim($rawInstagram) !== $instagramHandle) {
                update_user_meta($userId, 'instagram', $instagramHandle);
                update_user_meta($userId, 'instagram_url', 'https://instagram.com/' . $instagramHandle);
            }
        }

        $tiktokHandle = $fields['tiktok'];
        if ($tiktokHandle !== '') {
            $rawTiktok = (string) get_user_meta($userId, 'tiktok', true);
            $currentTikTok = AttendeeFields::sanitizeValue('tiktok', $rawTiktok);
            if ($force || $currentTikTok === '' || $currentTikTok !== $tiktokHandle || trim($rawTiktok) !== $tiktokHandle) {
                update_user_meta($userId, 'tiktok', $tiktokHandle);
                update_user_meta($userId, 'tiktok_url', 'https://www.tiktok.com/@' . $tiktokHandle);
            }
        }

        if ($fields['facebook'] !== '') {
            $rawFacebook = (string) get_user_meta($userId, 'facebook', true);
            $currentFacebook = AttendeeFields::sanitizeValue('facebook', $rawFacebook);
            if ($force || $currentFacebook === '' || $currentFacebook !== $fields['facebook'] || trim($rawFacebook) !== $fields['facebook']) {
                update_user_meta($userId, 'facebook', $fields['facebook']);
                update_user_meta($userId, 'facebook_url', $fields['facebook']);
            }
        }

        if ($fields['birth_date'] !== '') {
            $this->maybeUpdateMeta($userId, 'birth_date', $fields['birth_date'], $force);
            $this->maybeUpdateMeta($userId, 'um_birth_date', $fields['birth_date'], $force);
        }

        if ($fields['gender'] !== '') {
            $this->maybeUpdateMeta($userId, 'gender', $fields['gender'], $force);
            $this->maybeUpdateMeta($userId, 'um_gender', $fields['gender'], $force);
        }

        if ($fields['id_number'] !== '') {
            $this->maybeUpdateMeta($userId, 'id_number', $fields['id_number'], $force);
        }

        $user = get_userdata($userId);
        if ($user) {
            $displayCandidates = trim(($fields['first_name'] ? $fields['first_name'] . ' ' : '') . $fields['last_name']);
            if ($displayCandidates !== '') {
                $currentDisplay = trim((string) $user->display_name);
                $shouldUpdateDisplay = $force
                    || $currentDisplay === ''
                    || $currentDisplay === $user->user_login
                    || $currentDisplay === $user->user_email;

                if ($shouldUpdateDisplay) {
                    wp_update_user([
                        'ID'           => $userId,
                        'display_name' => $displayCandidates,
                        'nickname'     => $displayCandidates,
                    ]);
                }
            }
        }
    }

    public function loginRedirectUrl(): string
    {
        $checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');

        if (function_exists('wc_get_page_permalink')) {
            $accountUrl = wc_get_page_permalink('myaccount');
            if ($accountUrl) {
                return add_query_arg('redirect_to', rawurlencode($checkout), $accountUrl);
            }
        }

        return wp_login_url($checkout);
    }

    private function maybeUpdateMeta(int $userId, string $metaKey, string $value, bool $force): void
    {
        if ($value === '') {
            return;
        }

        $current = get_user_meta($userId, $metaKey, true);
        $currentString = is_scalar($current) ? (string) $current : '';
        if (!$force && trim($currentString) !== '') {
            return;
        }

        update_user_meta($userId, $metaKey, $value);
    }
}
