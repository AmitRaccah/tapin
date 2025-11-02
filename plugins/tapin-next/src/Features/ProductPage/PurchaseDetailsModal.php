<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\ProductPage\PurchaseModal\PurchaseModalCheckout;
use Tapin\Events\Features\ProductPage\PurchaseModal\PurchaseModalDataProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\PurchaseModalView;

final class PurchaseDetailsModal implements Service
{
    private const SCRIPT_HANDLE       = 'tapin-purchase-modal';
    private const STYLE_HANDLE        = 'tapin-purchase-modal';
    private const SESSION_KEY_PENDING = 'tapin_pending_checkout';

    private PurchaseModalDataProvider $data;
    private PurchaseModalView $view;
    private PurchaseModalCheckout $checkout;

    public function __construct(
        ?PurchaseModalDataProvider $data = null,
        ?PurchaseModalView $view = null,
        ?PurchaseModalCheckout $checkout = null
    ) {
        $this->data     = $data ?? new PurchaseModalDataProvider();
        $this->view     = $view ?? new PurchaseModalView($this->data, __FILE__, self::SCRIPT_HANDLE, self::STYLE_HANDLE);
        $this->checkout = $checkout ?? new PurchaseModalCheckout($this->data, self::SESSION_KEY_PENDING);
    }

    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_filter('woocommerce_product_single_add_to_cart_text', [$this->view, 'filterButtonText'], 10, 2);
        add_action('wp_enqueue_scripts', [$this->view, 'enqueueAssets']);
        add_action('woocommerce_before_add_to_cart_button', [$this->view, 'renderHiddenField'], 5);
        add_action('woocommerce_after_add_to_cart_form', [$this->view, 'renderModal'], 10);

        add_filter('woocommerce_add_to_cart_validation', [$this->checkout, 'validateSubmission'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this->checkout, 'attachCartItemData'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this->checkout, 'restoreCartItemFromSession'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this->checkout, 'displayCartItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this->checkout, 'storeOrderItemMeta'], 10, 4);
        add_filter('woocommerce_hidden_order_itemmeta', [$this->checkout, 'hideOrderItemMeta'], 10, 1);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this->checkout, 'filterFormattedMeta'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this->checkout, 'applyAttendeePricing'], 20);

        add_filter('woocommerce_add_to_cart_redirect', [$this->checkout, 'maybeRedirectToCheckout'], 10, 2);
        add_action('init', [$this->checkout, 'maybeResumePendingCheckout'], 20);
    }
}

