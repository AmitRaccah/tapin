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
    public function storePendingCheckout(array $attendees, int $productId, int $quantity): void
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
            'created_by_login_redirect' => true,
            'timestamp'  => time(),
        ];

        $session->set(Constants::SESSION_KEY_PENDING, $payload);
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

        $productId = (int) ($pending['product_id'] ?? 0);
        $quantity = max(1, (int) ($pending['quantity'] ?? 1));
        $rawAttendees = is_array($pending['attendees']) ? $pending['attendees'] : [];

        $errors = [];
        $sanitized = [];
        $ticketTypeIndex = $this->ticketTypeCache->getTicketTypeIndex($productId);

        foreach ($rawAttendees as $index => $entry) {
            $result = $this->sanitizer->sanitizeAttendee(is_array($entry) ? $entry : [], $index, $errors, $index === 0, $ticketTypeIndex);
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
            !$this->guards->isProductPurchasable($productId)
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

        if ($cartItemKey) {
            $payer = $sanitized[0] ?? [];
            $this->userManager->maybeUpdateUserProfile(get_current_user_id(), $payer, false);
        }
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
}
