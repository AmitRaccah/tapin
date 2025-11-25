<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Checkout;

use Tapin\Events\Features\ProductPage\PurchaseModal\Constants;
use Tapin\Events\Features\ProductPage\PurchaseModal\Guards\ProductGuards;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Features\ProductPage\PurchaseModal\Users\TransparentUserManager;
use Tapin\Events\Features\ProductPage\PurchaseModal\Validation\AttendeeSanitizer;
use Tapin\Events\Support\TicketTypeTracer;
use WC_Product;

final class PendingCheckoutManager
{
    private const PENDING_CHECKOUT_TTL = 900;

    private FlowState $flowState;
    private AttendeeSanitizer $sanitizer;
    private TicketTypeCache $ticketTypeCache;
    private TransparentUserManager $userManager;
    private ProductGuards $guards;

    public function __construct(
        FlowState $flowState,
        AttendeeSanitizer $sanitizer,
        TicketTypeCache $ticketTypeCache,
        TransparentUserManager $userManager,
        ProductGuards $guards
    ) {
        $this->flowState = $flowState;
        $this->sanitizer = $sanitizer;
        $this->ticketTypeCache = $ticketTypeCache;
        $this->userManager = $userManager;
        $this->guards = $guards;
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     */
    public function storePendingCheckout(array $attendees, int $productId, int $quantity, int $userId = 0): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $session = WC()->session;
        if (!$session) {
            return;
        }

        $payload = [
            'product_id'               => (int) $productId,
            'quantity'                 => max(1, (int) $quantity),
            'attendees'                => array_values($attendees),
            'user_id'                  => max(0, (int) $userId),
            'created_by_login_redirect' => true,
            'created_at'               => time(),
        ];

        $normalized = $this->normalizePendingPayload($payload);
        $normalized['hmac'] = $this->buildPendingSignature($normalized);

        $session->set(Constants::SESSION_KEY_PENDING, $normalized);
    }

    public function redirectToLogin(): void
    {
        $url = $this->userManager->loginRedirectUrl();
        if ($url === '') {
            $url = home_url('/');
        }

        wp_safe_redirect($url);
        exit;
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

        $pending = $session->get(Constants::SESSION_KEY_PENDING);
        if (!is_array($pending) || empty($pending['attendees']) || empty($pending['product_id']) || empty($pending['created_by_login_redirect'])) {
            return;
        }

        $session->set(Constants::SESSION_KEY_PENDING, null);

        $normalized = $this->normalizePendingPayload($pending);
        $productId  = (int) ($normalized['product_id'] ?? 0);
        $quantity   = max(1, (int) ($normalized['quantity'] ?? 1));
        $rawAttendees = is_array($normalized['attendees']) ? $normalized['attendees'] : [];

        $createdAt = (int) ($normalized['created_at'] ?? 0);
        if ($createdAt <= 0 || (time() - $createdAt) > self::PENDING_CHECKOUT_TTL) {
            $this->logPendingIssue($productId, 'expired');
            return;
        }

        $sessionUserId   = get_current_user_id();
        $expectedUserId  = (int) ($normalized['user_id'] ?? 0);
        $storedSignature = is_string($pending['hmac'] ?? null) ? (string) $pending['hmac'] : '';

        if ($expectedUserId > 0 && $expectedUserId !== $sessionUserId) {
            $this->logPendingIssue($productId, 'user-mismatch');
            return;
        }

        $calculatedSignature = $this->buildPendingSignature($normalized);
        if ($calculatedSignature === '' || $storedSignature === '' || !hash_equals($calculatedSignature, $storedSignature)) {
            $this->logPendingIssue($productId, 'signature-invalid');
            return;
        }

        $validation = $this->validateAttendeesPayload($productId, $quantity, $rawAttendees);
        $errors = $validation['errors'];
        $sanitized = $validation['sanitized'];

        if ($errors !== [] || $sanitized === []) {
            foreach ($errors as $message) {
                wc_add_notice($message, 'error');
            }
            $this->logPendingIssue($productId, 'validation');
            return;
        }

        $quantity = count($sanitized);

        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($productId);
        if (
            !$product instanceof WC_Product ||
            !$product->is_purchasable() ||
            !$this->guards->isProductPurchasable($productId)
        ) {
            if (function_exists('tapin_next_debug_log')) {
                tapin_next_debug_log(sprintf('[pending-checkout] resume failed: product %d not purchasable', $productId));
            }
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
            $_POST['quantity'] = (string) $quantity;
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

        if (class_exists(TicketTypeTracer::class)) {
            TicketTypeTracer::resume((int) $productId, (int) count($sanitized), (bool) $cartItemKey);
        }

        $currentUserId = get_current_user_id();
        $added = (bool) $cartItemKey;
        /**
         * Fires after Tapin tries to resume a checkout after login/redirect.
         */
        do_action('tapin/events/checkout/pending_resumed', $productId, $quantity, $added, $currentUserId);
        if (function_exists('tapin_next_debug_log')) {
            tapin_next_debug_log(sprintf('[pending-checkout] resume %s for product %d', $added ? 'succeeded' : 'failed', $productId));
        }

        if ($added) {
            $payer = $sanitized[0] ?? [];
            $this->userManager->maybeUpdateUserProfile($currentUserId, $payer, false);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rawAttendees
     * @return array{sanitized: array<int,array<string,mixed>>, errors: array<int,string>}
     */
    public function validateAttendeesPayload(int $productId, int $quantity, array $rawAttendees): array
    {
        $errors = [];
        $sanitized = [];
        $cache = $this->ticketTypeCache->ensureTicketTypeCache($productId);
        $ticketTypeIndex = $cache['index'];

        if ($quantity > 0 && count($rawAttendees) !== $quantity) {
            $errors[] = __('כמות המשתתפים אינה תואמת את הכמות שנבחרה.', 'tapin');
        }

        foreach ($rawAttendees as $index => $entry) {
            $result = $this->sanitizer->sanitizeAttendee(is_array($entry) ? $entry : [], $index, $errors, $index === 0, $ticketTypeIndex);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($sanitized === []) {
            $errors[] = __('לא נבחרו משתתפים תקינים להמשך הזמנה.', 'tapin');
        }

        $typeCounts = [];
        foreach ($sanitized as $entry) {
            $typeId = isset($entry['ticket_type']) ? (string) $entry['ticket_type'] : '';
            if ($typeId === '' || !isset($ticketTypeIndex[$typeId])) {
                $errors[] = __('סוג הכרטיס שנבחר אינו זמין.', 'tapin');
                continue;
            }
            $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
        }

        foreach ($typeCounts as $typeId => $count) {
            $context = $ticketTypeIndex[$typeId];
            $capacity = isset($context['capacity']) ? (int) $context['capacity'] : 0;
            $available = isset($context['available']) ? (int) $context['available'] : 0;

            if ($capacity > 0 && $available >= 0 && $count > $available) {
                $name = (string) ($context['name'] ?? $typeId);
                $errors[] = sprintf(__('אין מספיק כרטיסי %s זמינים.', 'tapin'), $name !== '' ? $name : $typeId);
            }
        }

        return [
            'sanitized' => $sanitized,
            'errors'    => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param mixed $product
     */
    public function maybeRedirectToCheckout($url, $product)
    {
        $shouldRedirect = $this->flowState->consumeRedirectFlag();

        if (!$shouldRedirect && isset($_POST['tapin_attendees'])) {
            if (!function_exists('wc_get_notices') || wc_get_notices('error') === []) {
                $shouldRedirect = true;
            }
        }

        if (!$shouldRedirect) {
            return $url;
        }

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }

        return $url;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePendingPayload(array $payload): array
    {
        $attendees = [];
        if (isset($payload['attendees']) && is_array($payload['attendees'])) {
            $attendees = array_values($payload['attendees']);
        }

        return [
            'product_id'               => (int) ($payload['product_id'] ?? 0),
            'quantity'                 => max(1, (int) ($payload['quantity'] ?? 1)),
            'attendees'                => $attendees,
            'user_id'                  => max(0, (int) ($payload['user_id'] ?? 0)),
            'created_at'               => (int) ($payload['created_at'] ?? ($payload['timestamp'] ?? 0)),
            'created_by_login_redirect' => !empty($payload['created_by_login_redirect']),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildPendingSignature(array $payload): string
    {
        $normalized = [
            'product_id' => (int) ($payload['product_id'] ?? 0),
            'quantity'   => max(1, (int) ($payload['quantity'] ?? 1)),
            'user_id'    => max(0, (int) ($payload['user_id'] ?? 0)),
            'created_at' => (int) ($payload['created_at'] ?? 0),
            'attendees'  => isset($payload['attendees']) && is_array($payload['attendees']) ? array_values($payload['attendees']) : [],
            'redirect'   => !empty($payload['created_by_login_redirect']),
        ];

        $json = wp_json_encode($normalized);
        if (!is_string($json)) {
            return '';
        }

        $secret = function_exists('wp_salt') ? wp_salt('tapin_pending_checkout') : (defined('AUTH_SALT') ? AUTH_SALT : 'tapin_pending_checkout');

        return hash_hmac('sha256', $json, (string) $secret);
    }

    private function logPendingIssue(int $productId, string $reason): void
    {
        if (function_exists('tapin_next_debug_log')) {
            tapin_next_debug_log(sprintf('[pending-checkout] resume aborted for product %d: %s', $productId, $reason));
        }
    }
}
