<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class AbandonedCheckoutCartCleanup implements Service
{
    private const SESSION_KEY = 'tapin_checkout_in_progress';

    public function register(): void
    {
        if (!function_exists('WC')) {
            return;
        }

        add_action('woocommerce_checkout_init', [$this, 'markCheckoutStarted'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'clearCheckoutState'], 10, 1);
        add_action('woocommerce_cart_emptied', [$this, 'clearCheckoutState'], 10, 0);
        add_action('template_redirect', [$this, 'maybeEmptyAbandonedCart'], 20);
    }

    public function markCheckoutStarted($checkout): void
    {
        if (wp_doing_ajax() || !function_exists('WC') || !WC()->session) {
            return;
        }

        $cartHash = '';
        if (WC()->cart instanceof \WC_Cart && !WC()->cart->is_empty()) {
            $cartHash = (string) WC()->cart->get_cart_hash();
        }

        WC()->session->set(self::SESSION_KEY, [
            'started_at' => time(),
            'cart_hash'  => $cartHash,
        ]);
    }

    public function clearCheckoutState($orderId = 0): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        WC()->session->set(self::SESSION_KEY, null);
    }

    public function maybeEmptyAbandonedCart(): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        if ((function_exists('is_checkout') && is_checkout()) ||
            (function_exists('is_order_received_page') && is_order_received_page())
        ) {
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $state = WC()->session->get(self::SESSION_KEY);
        if (!is_array($state)) {
            return;
        }

        WC()->session->set(self::SESSION_KEY, null);

        if (!WC()->cart instanceof \WC_Cart || WC()->cart->is_empty()) {
            return;
        }

        $expectedHash = isset($state['cart_hash']) ? (string) $state['cart_hash'] : '';
        $currentHash  = (string) WC()->cart->get_cart_hash();

        // אם בינתיים נבנתה עגלה חדשה – לא נוגעים
        if ($expectedHash !== '' && $expectedHash !== $currentHash) {
            return;
        }

        WC()->cart->empty_cart();
    }
}
