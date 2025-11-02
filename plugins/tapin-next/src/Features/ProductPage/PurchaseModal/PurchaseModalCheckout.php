<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal;

use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\ProductAvailability;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class PurchaseModalCheckout
{
    private PurchaseModalDataProvider $data;
    private string $pendingSessionKey;
    /** @var array<int,array<string,mixed>> */
    private array $pendingAttendees = [];
    /** @var array<int,array<string,mixed>> */
    private array $attendeeQueue = [];
    private bool $processingSplitAdd = false;
    private bool $redirectNextAdd = false;
    /** @var array<string,array<string,mixed>> */
    private array $currentTicketTypeIndex = [];

    public function __construct(PurchaseModalDataProvider $data, string $pendingSessionKey)
    {
        $this->data              = $data;
        $this->pendingSessionKey = $pendingSessionKey;
    }

    public function validateSubmission(bool $passed, int $productId, int $quantity, $variationId = 0, $variations = null): bool
    {
        $this->redirectNextAdd = false;
        if ($this->processingSplitAdd) {
            return $passed;
        }

        $this->pendingAttendees = [];
        $this->attendeeQueue    = [];

        if ((is_admin() && !wp_doing_ajax()) || !$this->data->shouldHandleProduct($productId)) {
            return $passed;
        }

        if (!isset($_POST['tapin_attendees'])) {
            wc_add_notice('?T?c ???????? ???x ???"?~?T ?"???c?x?x???T?? ?????�?T ?"?"?>?T?c?".', 'error');
            return false;
        }

        $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
        if (!is_array($decoded)) {
            wc_add_notice('?�?"???" ?c?T?c ?`?�?T?" ?`?�?x??�?T ?"???c?x?x???T??, ???�?? ?�?�? ?c??`.', 'error');
            return false;
        }

        $quantity = max(1, (int) $quantity);
        if (count($decoded) !== $quantity) {
            wc_add_notice('?T?c ???"?-?T?? ???"?~?T?? ?�?`??" ?>?? ???c?x?x?� ?c?�?`?-?" ???"?>?T?c?".', 'error');
            return false;
        }

        $cache = $this->data->ensureTicketTypeCache($productId);
        $this->currentTicketTypeIndex = $cache['index'];

        $sanitized = [];
        $errors    = [];

        foreach ($decoded as $index => $attendee) {
            $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                wc_add_notice($message, 'error');
            }

            return false;
        }

        if ($sanitized === []) {
            wc_add_notice(__('???? ?�???�??? ???c?x?x???T?? ?x??T?�?T?? ???�?T?`??". ???�?? ?�?�? ?c??`.', 'tapin'), 'error');
            return false;
        }

        $typeCounts = [];
        foreach ($sanitized as $entry) {
            $typeId = isset($entry['ticket_type']) ? (string) $entry['ticket_type'] : '';
            if ($typeId === '' || !isset($this->currentTicketTypeIndex[$typeId])) {
                wc_add_notice(__('?�??' ?"?>?"?~?T?� ?c?�?`?-?" ???T?�? ?-???T??.', 'tapin'), 'error');
                return false;
            }
            $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
        }

        foreach ($typeCounts as $typeId => $count) {
            $context   = $this->currentTicketTypeIndex[$typeId];
            $capacity  = isset($context['capacity']) ? (int) $context['capacity'] : 0;
            $available = isset($context['available']) ? (int) $context['available'] : 0;
            if ($capacity > 0 && $available >= 0 && $count > $available) {
                $name = (string) ($context['name'] ?? $typeId);
                wc_add_notice(sprintf(__('???T?? ???�???T? ?-???T?�??x ?�?`??" %s.', 'tapin'), esc_html($name)), 'error');
                return false;
            }
        }

        $payer   = $sanitized[0];
        $userId  = get_current_user_id();
        $created = false;

        if (!$userId) {
            $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
            if ($email === '') {
                wc_add_notice('?>?x??`?x ?"???T???T?T?? ???T?�?" ?x??T?�?".', 'error');
                return false;
            }

            $existing = get_user_by('email', $email);
            if ($existing instanceof \WP_User) {
                $this->storePendingCheckout($sanitized, $productId, $quantity);
                $this->pendingAttendees = [];
                $this->redirectToLogin();
                return false;
            }

            $userId = $this->createTransparentUser($payer);
            if (!$userId) {
                return false;
            }

            $created = true;
        }

        $this->maybeUpdateUserProfile((int) $userId, $payer, $created);

        $this->pendingAttendees = $sanitized;
        $this->attendeeQueue    = $sanitized;
        $_POST['quantity']      = 1;
        $_REQUEST['quantity']   = 1;
        return $passed;
    }

    public function attachCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $isGenerated = !empty($cartItemData['tapin_split_generated']);
        $attendees   = [];

        if ($isGenerated && isset($cartItemData['tapin_attendees']) && is_array($cartItemData['tapin_attendees'])) {
            $attendees = array_values(array_filter($cartItemData['tapin_attendees'], 'is_array'));
        } else {
            if ($this->attendeeQueue !== []) {
                $next = array_shift($this->attendeeQueue);
                if (is_array($next)) {
                    $attendees[] = $next;
                }
            } elseif ($this->pendingAttendees !== []) {
                $next = array_shift($this->pendingAttendees);
                if (is_array($next)) {
                    $attendees[] = $next;
                }
            }

            if ($attendees === [] && isset($_POST['tapin_attendees'])) {
                $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
                if (is_array($decoded)) {
                    $errors  = [];
                    $rebuilt = [];
                    foreach ($decoded as $index => $attendee) {
                        $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0);
                        if ($result !== null) {
                            $rebuilt[] = $result;
                        }
                    }

                    if ($errors !== []) {
                        foreach ($errors as $message) {
                            wc_add_notice($message, 'error');
                        }

                        return $cartItemData;
                    }

                    $this->attendeeQueue    = $rebuilt;
                    $this->pendingAttendees = $rebuilt;
                    $next = array_shift($this->attendeeQueue);
                    if (is_array($next)) {
                        $attendees[] = $next;
                    }
                }
            }
        }

        if ($attendees === []) {
            unset($cartItemData['tapin_split_generated']);
            return $cartItemData;
        }

        $attendee = $attendees[0];
        $price    = isset($attendee['ticket_price']) ? (float) $attendee['ticket_price'] : null;

        $cartItemData['tapin_attendees']    = [$attendee];
        $cartItemData['tapin_attendees_key'] = md5(wp_json_encode($attendee) . microtime(true));
        if ($price !== null) {
            $cartItemData['tapin_ticket_price'] = $price;
        }

        if (!$isGenerated) {
            $this->redirectNextAdd = true;

            $remaining              = $this->attendeeQueue;
            $this->attendeeQueue    = [];
            $this->pendingAttendees = [];

            if (!empty($remaining) && function_exists('WC') && WC()->cart instanceof \WC_Cart) {
                $this->processingSplitAdd = true;
                foreach ($remaining as $extraAttendee) {
                    if (!is_array($extraAttendee)) {
                        continue;
                    }

                    $extraPrice = isset($extraAttendee['ticket_price']) ? (float) $extraAttendee['ticket_price'] : null;
                    $extraData  = [
                        'tapin_attendees'        => [$extraAttendee],
                        'tapin_attendees_key'    => md5(wp_json_encode($extraAttendee) . microtime(true)),
                        'tapin_split_generated'  => true,
                    ];

                    if ($extraPrice !== null) {
                        $extraData['tapin_ticket_price'] = $extraPrice;
                    }

                    WC()->cart->add_to_cart($productId, 1, $variationId, [], $extraData);
                }
                $this->processingSplitAdd = false;
            }
        }

        unset($cartItemData['tapin_split_generated']);

        return $cartItemData;
    }

    public function restoreCartItemFromSession(array $item, array $values): array
    {
        if (isset($values['tapin_attendees'])) {
            $item['tapin_attendees'] = $values['tapin_attendees'];
        }

        if (isset($values['tapin_ticket_price'])) {
            $item['tapin_ticket_price'] = (float) $values['tapin_ticket_price'];
        }

        if (isset($values['tapin_attendees_key'])) {
            $item['tapin_attendees_key'] = $values['tapin_attendees_key'];
        }

        if (!empty($values['tapin_split_generated'])) {
            $item['tapin_split_generated'] = true;
        }

        return $item;
    }

    public function displayCartItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['tapin_attendees']) || !is_array($cartItem['tapin_attendees'])) {
            return $itemData;
        }

        $primary = $cartItem['tapin_attendees'][0] ?? [];
        $fields  = AttendeeFields::cartDisplay();

        foreach ($fields as $key => $label) {
            if (!isset($primary[$key]) || $primary[$key] === '') {
                continue;
            }

            $itemData[] = [
                'key'   => $label,
                'value' => AttendeeFields::displayValue($key, (string) $primary[$key]),
            ];
        }

        return $itemData;
    }

    public function hideOrderItemMeta(array $hiddenKeys): array
    {
        $hiddenKeys[] = '_tapin_attendees_json';
        $hiddenKeys[] = '_tapin_attendees_key';
        $hiddenKeys[] = '_tapin_ticket_price';
        return $hiddenKeys;
    }

    public function filterFormattedMeta(array $metaData, $item): array
    {
        foreach ($metaData as $index => $meta) {
            if (!isset($meta->key) || !is_string($meta->key)) {
                continue;
            }

            if (strpos($meta->key, '???c?x?x?�') === 0) {
                unset($metaData[$index]);
            }
        }

        return array_values($metaData);
    }

    public function storeOrderItemMeta($item, string $cartItemKey, array $values, $order): void
    {
        if (empty($values['tapin_attendees']) || !is_array($values['tapin_attendees'])) {
            return;
        }

        $normalizedAttendees = array_map(function (array $attendee): array {
            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $clean[$key] = AttendeeFields::sanitizeValue($key, (string) ($attendee[$key] ?? ''));
            }
            return $clean;
        }, $values['tapin_attendees']);

        foreach ($values['tapin_attendees'] as $offset => $attendee) {
            if (!isset($normalizedAttendees[$offset])) {
                continue;
            }
            if (isset($attendee['ticket_price'])) {
                $normalizedAttendees[$offset]['ticket_price'] = (float) $attendee['ticket_price'];
            }
        }

        if (isset($values['tapin_ticket_price'])) {
            $item->update_meta_data('_tapin_ticket_price', (float) $values['tapin_ticket_price']);
        }

        $encryptedAttendees = AttendeeSecureStorage::encryptAttendees($normalizedAttendees);
        if ($encryptedAttendees !== '') {
            $item->update_meta_data('_tapin_attendees_json', $encryptedAttendees);
        }

        if (!empty($values['tapin_attendees_key'])) {
            $item->update_meta_data('_tapin_attendees_key', sanitize_text_field((string) $values['tapin_attendees_key']));
        }

        $item->delete_meta_data('Tapin Attendees');

        $maskedAttendees = AttendeeSecureStorage::maskAttendees($normalizedAttendees);

        foreach ($maskedAttendees as $index => $attendee) {
            $label = sprintf(__('???c?x?x?� %d', 'tapin'), $index + 1);
            $parts = [];
            foreach (AttendeeFields::summaryKeys() as $key) {
                $parts[] = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            }
            $item->update_meta_data($label, implode(' | ', $parts));
        }

        if ($order instanceof WC_Order) {
            $existing = AttendeeSecureStorage::upgradeAggregate($order->get_meta('_tapin_attendees', true));

            $bucketKey = $item->get_id() ?: ($values['tapin_attendees_key'] ?? $cartItemKey);
            $bucketKey = (string) $bucketKey;

            $existing['line_items'][$bucketKey] = [
                'item_id'    => (int) $item->get_id(),
                'source_key' => (string) ($values['tapin_attendees_key'] ?? $cartItemKey),
                'encrypted'  => $encryptedAttendees,
                'masked'     => $maskedAttendees,
                'count'      => count($normalizedAttendees),
                'updated'    => current_time('mysql'),
            ];

            $order->update_meta_data('_tapin_attendees', $existing);
        }
    }

    /**
     * @param mixed $product
     */
    public function maybeRedirectToCheckout($url, $product)
    {
        $shouldRedirect = $this->redirectNextAdd;

        if (!$shouldRedirect && isset($_POST['tapin_attendees'])) {
            if (!function_exists('wc_get_notices') || wc_get_notices('error') === []) {
                $shouldRedirect = true;
            }
        }

        if (!$shouldRedirect) {
            return $url;
        }

        $this->redirectNextAdd = false;

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }

        return $url;
    }

    public function applyAttendeePricing($cart): void
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $key => $item) {
            $price = null;
            if (isset($item['tapin_ticket_price'])) {
                $price = (float) $item['tapin_ticket_price'];
            } elseif (!empty($item['tapin_attendees'][0]['ticket_price'])) {
                $price = (float) $item['tapin_attendees'][0]['ticket_price'];
            }

            if ($price === null) {
                continue;
            }

            if (isset($cart->cart_contents[$key])) {
                $cart->cart_contents[$key]['tapin_ticket_price'] = $price;
            }

            if (isset($item['data']) && $item['data'] instanceof WC_Product) {
                $item['data']->set_price($price);
            }
        }
    }

    public function maybeResumePendingCheckout(): void
    {
        if (!is_user_logged_in() || !function_exists('WC')) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $session = WC()->session;
        if (!$session) {
            return;
        }

        $pending = $session->get($this->pendingSessionKey);
        if (!is_array($pending) || empty($pending['attendees']) || empty($pending['product_id'])) {
            return;
        }

        $session->set($this->pendingSessionKey, null);

        $productId    = (int) ($pending['product_id'] ?? 0);
        $quantity     = max(1, (int) ($pending['quantity'] ?? 1));
        $rawAttendees = is_array($pending['attendees']) ? $pending['attendees'] : [];

        $cache = $this->data->ensureTicketTypeCache($productId);
        $this->currentTicketTypeIndex = $cache['index'];

        $errors    = [];
        $sanitized = [];
        foreach ($rawAttendees as $index => $entry) {
            $result = $this->sanitizeAttendee(is_array($entry) ? $entry : [], $index, $errors, $index === 0);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($errors !== [] || $sanitized === []) {
            return;
        }

        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($productId);
        if (
            !$product instanceof WC_Product ||
            !$product->is_purchasable() ||
            !ProductAvailability::isCurrentlyPurchasable($productId)
        ) {
            return;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $payload = wp_json_encode($sanitized);
        if (is_string($payload)) {
            $_POST['tapin_attendees'] = $payload;
            $_POST['quantity']        = (string) $quantity;
        }

        $cartItemKey = $cart->add_to_cart(
            $productId,
            $quantity,
            0,
            [],
            [
                'tapin_attendees'     => $sanitized,
                'tapin_attendees_key' => md5(wp_json_encode($sanitized) . microtime(true)),
            ]
        );

        unset($_POST['tapin_attendees'], $_POST['quantity']);

        if ($cartItemKey) {
            $payer = $sanitized[0] ?? [];
            $this->maybeUpdateUserProfile(get_current_user_id(), $payer, false);
        }
    }

    private function sanitizeAttendee(array $attendee, int $index, array &$errors, bool $isPayer): ?array
    {
        $definitions   = $this->data->getFieldDefinitions();
        $clean         = [];
        $attendeeLabel = $this->attendeeLabel($index);

        foreach ($definitions as $key => $definition) {
            $raw    = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            $raw    = is_string($raw) ? $raw : '';
            $hasRaw = trim($raw) !== '';
            $value  = AttendeeFields::sanitizeValue($key, $raw);

            if ($value === '' && $hasRaw) {
                $display = AttendeeFields::displayValue($key, $raw);
                if ($display !== '') {
                    $fallback = AttendeeFields::sanitizeValue($key, $display);
                    if ($fallback !== '') {
                        $value = $fallback;
                    }
                }
            }

            $requirements = isset($definition['required_for']) && is_array($definition['required_for'])
                ? $definition['required_for']
                : ['payer' => true, 'attendee' => true];
            $isRequired = $isPayer ? !empty($requirements['payer']) : !empty($requirements['attendee']);

            if ($value === '') {
                if ($hasRaw) {
                    $errors[] = $this->invalidFieldMessage($key, (string) ($definition['label'] ?? $key), $attendeeLabel);
                    return null;
                }

                if ($isRequired) {
                    $errors[] = $this->missingFieldMessage($key, (string) ($definition['label'] ?? $key), $attendeeLabel);
                    return null;
                }

                $clean[$key] = '';
                continue;
            }

            $clean[$key] = $value;
        }

        $ticketTypeId    = isset($attendee['ticket_type']) ? sanitize_key((string) $attendee['ticket_type']) : '';
        $ticketTypeLabel = '';
        $ticketPrice     = 0.0;
        if ($ticketTypeId !== '' && isset($this->currentTicketTypeIndex[$ticketTypeId])) {
            $context = $this->currentTicketTypeIndex[$ticketTypeId];
            $ticketTypeLabel = (string) ($context['name'] ?? '');
            $ticketPrice     = isset($context['price']) ? (float) $context['price'] : 0.0;
        }

        $clean['ticket_type']  = $ticketTypeId;
        $clean['ticket_label'] = $ticketTypeLabel;
        $clean['ticket_price'] = $ticketPrice;

        return $clean;
    }

    private function missingFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf('?T?c ???"?-?T?? ???T???T?T?? ?�?`??" %s', $attendeeLabel);
            case 'instagram':
                return sprintf('?T?c ???"?-?T?? ???T?�?�?~?'?"?? ?�?`??" %s', $attendeeLabel);
            case 'phone':
                return sprintf('?T?c ???"?-?T?? ???�???" ?~??????? ?�?`??" %s', $attendeeLabel);
            default:
                return sprintf('?T?c ???????? ???x ?"?c?"?" %s ?�?`??" %s', $fieldLabel, $attendeeLabel);
        }
    }

    private function invalidFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf('?>?x??`?x ?"???T???T?T?? ???T?�?" ?x??T?�?" ?�?`??" %s', $attendeeLabel);
            case 'phone':
                return sprintf('???�???" ?"?~??????? ?-?T?T?` ???>????? ?????-??x 10 ?�???"??x ?�?`??" %s', $attendeeLabel);
            case 'id_number':
                return sprintf('?x?�??"?x ?-?"??x ?-?T?T?`?x ???"?>?T?? ?`?"?T?? 9 ?�???"??x ?�?`??" %s', $attendeeLabel);
            case 'instagram':
                return sprintf('?T?c ???"?-?T?? ???T?�?�?~?'?"?? ?x??T?? (@username ??? ??T?c??" ?????"????T??) ?�?`??" %s', $attendeeLabel);
            case 'tiktok':
                return sprintf('?T?c ???"?-?T?? ?~?T??~?? ?x??T?? (@username ??? ??T?c??" ?????"????T??) ?�?`??" %s', $attendeeLabel);
            case 'facebook':
                return sprintf('??T?c??" ?"???T?T?�?`?? ?-?T?T?` ???>????? facebook ?�?`??" %s', $attendeeLabel);
            default:
                return sprintf('?"?�?"?? ?c?�???? ?�?`??" %s ???T?�? ?x??T?? ?�?`??" %s', $fieldLabel, $attendeeLabel);
        }
    }

    private function attendeeLabel(int $index): string
    {
        return $index === 0 ? '?"?????- ?"???c????' : sprintf('???c?x?x?� %d', $index + 1);
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     */
    private function storePendingCheckout(array $attendees, int $productId, int $quantity): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $session = WC()->session;
        if (!$session) {
            return;
        }

        $payload = [
            'product_id' => (int) $productId,
            'quantity'   => max(1, (int) $quantity),
            'attendees'  => $attendees,
            'timestamp'  => time(),
        ];

        $session->set($this->pendingSessionKey, $payload);
    }

    private function redirectToLogin(): void
    {
        $url = $this->loginRedirectUrl();
        if ($url === '') {
            $url = home_url('/');
        }

        wp_safe_redirect($url);
        exit;
    }

    private function loginRedirectUrl(): string
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

    private function createTransparentUser(array $payer): ?int
    {
        $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
        if ($email === '') {
            wc_add_notice('?>?x??`?x ?"???T???T?T?? ???T?�?" ?x??T?�?".', 'error');
            return null;
        }

        $firstName = sanitize_text_field($payer['first_name'] ?? '');
        $lastName  = sanitize_text_field($payer['last_name'] ?? '');
        $username  = $this->generateUsername($firstName, $lastName, $email);
        $password  = wp_generate_password(32, true);

        $userId = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'customer',
        ]);

        if (is_wp_error($userId)) {
            wc_add_notice('???? ?�?T?x?? ?"?T?" ???T?�??" ???c?x???c ?-?"?c, ???�?? ?�?�? ?c??` ??? ???�? ???x???T?>?".', 'error');
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

    private function generateUsername(string $firstName, string $lastName, string $email): string
    {
        $candidate = sanitize_user($firstName . $lastName, true);
        if ($candidate === '') {
            $parts     = explode('@', $email);
            $candidate = sanitize_user($parts[0] ?? '', true);
        }
        if ($candidate === '') {
            $candidate = 'tapin_user';
        }

        $username = $candidate;
        $suffix   = 1;
        while (username_exists($username)) {
            $username = $candidate . $suffix;
            $suffix++;
        }

        return $username;
    }

    private function maybeUpdateUserProfile(int $userId, array $payer, bool $force = false): void
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
            $rawInstagram  = (string) get_user_meta($userId, 'instagram', true);
            $currentHandle = AttendeeFields::sanitizeValue('instagram', $rawInstagram);
            if ($force || $currentHandle === '' || $currentHandle !== $instagramHandle || trim($rawInstagram) !== $instagramHandle) {
                update_user_meta($userId, 'instagram', $instagramHandle);
                update_user_meta($userId, 'instagram_url', 'https://instagram.com/' . $instagramHandle);
            }
        }

        $tiktokHandle = $fields['tiktok'];
        if ($tiktokHandle !== '') {
            $rawTiktok    = (string) get_user_meta($userId, 'tiktok', true);
            $currentTikTok = AttendeeFields::sanitizeValue('tiktok', $rawTiktok);
            if ($force || $currentTikTok === '' || $currentTikTok !== $tiktokHandle || trim($rawTiktok) !== $tiktokHandle) {
                update_user_meta($userId, 'tiktok', $tiktokHandle);
                update_user_meta($userId, 'tiktok_url', 'https://www.tiktok.com/@' . $tiktokHandle);
            }
        }

        if ($fields['facebook'] !== '') {
            $rawFacebook     = (string) get_user_meta($userId, 'facebook', true);
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
    }

    private function maybeUpdateMeta(int $userId, string $metaKey, string $value, bool $force): void
    {
        if ($value === '') {
            return;
        }

        $current = get_user_meta($userId, $metaKey, true);
        if (!$force && trim((string) $current) !== '') {
            return;
        }

        update_user_meta($userId, $metaKey, $value);
    }
}
