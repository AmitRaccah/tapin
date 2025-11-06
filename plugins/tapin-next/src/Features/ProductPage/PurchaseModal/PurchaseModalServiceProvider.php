<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\ProductPage\PurchaseModal\Assets\AssetsEnqueuer;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\CartItemHandler;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\FlowState;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\OrderMetaWriter;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\PendingCheckoutManager;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\PricingAdjuster;
use Tapin\Events\Features\ProductPage\PurchaseModal\Checkout\SubmissionValidator;
use Tapin\Events\Features\ProductPage\PurchaseModal\Fields\FieldDefinitionsProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Guards\ProductGuards;
use Tapin\Events\Features\ProductPage\PurchaseModal\Messaging\MessagesProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;
use Tapin\Events\Features\ProductPage\PurchaseModal\UI\ModalRenderer;
use Tapin\Events\Features\ProductPage\PurchaseModal\Users\TransparentUserManager;
use Tapin\Events\Features\ProductPage\PurchaseModal\Validation\AttendeeSanitizer;
use WC_Product;

final class PurchaseModalServiceProvider implements Service
{
    private ProductGuards $productGuards;
    private MessagesProvider $messages;
    private FieldDefinitionsProvider $fieldDefinitions;
    private TicketTypeCache $ticketTypeCache;
    private FlowState $flowState;
    private AttendeeSanitizer $attendeeSanitizer;
    private TransparentUserManager $userManager;
    private AssetsEnqueuer $assetsEnqueuer;
    private ModalRenderer $modalRenderer;
    private PricingAdjuster $pricingAdjuster;
    private CartItemHandler $cartItemHandler;
    private OrderMetaWriter $orderMetaWriter;
    private PendingCheckoutManager $pendingCheckoutManager;
    private SubmissionValidator $submissionValidator;

    public function __construct(
        ?ProductGuards $productGuards = null,
        ?MessagesProvider $messages = null,
        ?FieldDefinitionsProvider $fieldDefinitions = null,
        ?TicketTypeCache $ticketTypeCache = null,
        ?FlowState $flowState = null,
        ?AttendeeSanitizer $attendeeSanitizer = null,
        ?TransparentUserManager $userManager = null,
        ?AssetsEnqueuer $assetsEnqueuer = null,
        ?ModalRenderer $modalRenderer = null,
        ?PricingAdjuster $pricingAdjuster = null,
        ?CartItemHandler $cartItemHandler = null,
        ?OrderMetaWriter $orderMetaWriter = null,
        ?PendingCheckoutManager $pendingCheckoutManager = null,
        ?SubmissionValidator $submissionValidator = null
    ) {
        $this->productGuards = $productGuards ?? new ProductGuards();
        $this->messages = $messages ?? new MessagesProvider();
        $this->fieldDefinitions = $fieldDefinitions ?? new FieldDefinitionsProvider();
        $this->ticketTypeCache = $ticketTypeCache ?? new TicketTypeCache($this->messages);
        $this->flowState = $flowState ?? new FlowState();
        $this->attendeeSanitizer = $attendeeSanitizer ?? new AttendeeSanitizer($this->fieldDefinitions);
        $this->userManager = $userManager ?? new TransparentUserManager($this->fieldDefinitions, $this->attendeeSanitizer);
        $this->assetsEnqueuer = $assetsEnqueuer ?? new AssetsEnqueuer($this->productGuards, $this->ticketTypeCache, $this->messages, $this->userManager, $this->fieldDefinitions);
        $this->modalRenderer = $modalRenderer ?? new ModalRenderer($this->productGuards, $this->ticketTypeCache, $this->messages, $this->fieldDefinitions);
        $this->pricingAdjuster = $pricingAdjuster ?? new PricingAdjuster($this->ticketTypeCache);
        $this->cartItemHandler = $cartItemHandler ?? new CartItemHandler($this->attendeeSanitizer, $this->ticketTypeCache, $this->flowState);
        $this->orderMetaWriter = $orderMetaWriter ?? new OrderMetaWriter();
        $this->pendingCheckoutManager = $pendingCheckoutManager ?? new PendingCheckoutManager(
            $this->flowState,
            $this->attendeeSanitizer,
            $this->ticketTypeCache,
            $this->userManager,
            $this->productGuards
        );
        $this->submissionValidator = $submissionValidator ?? new SubmissionValidator(
            $this->attendeeSanitizer,
            $this->ticketTypeCache,
            $this->productGuards,
            $this->userManager,
            $this->pendingCheckoutManager,
            $this->flowState
        );
    }

    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'filterButtonText'], 10, 2);
        add_action('wp_enqueue_scripts', [$this->assetsEnqueuer, 'enqueueAssets']);
        add_action('woocommerce_before_add_to_cart_button', [$this->modalRenderer, 'renderHiddenField'], 5);
        add_action('woocommerce_after_add_to_cart_form', [$this->modalRenderer, 'renderModal'], 10);

        add_filter('woocommerce_add_to_cart_validation', [$this->submissionValidator, 'validateSubmission'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this->cartItemHandler, 'attachCartItemData'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this->cartItemHandler, 'restoreCartItemFromSession'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this->cartItemHandler, 'displayCartItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this->orderMetaWriter, 'storeOrderItemMeta'], 10, 4);
        add_filter('woocommerce_hidden_order_itemmeta', [$this->orderMetaWriter, 'hideOrderItemMeta'], 10, 1);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this->orderMetaWriter, 'filterFormattedMeta'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this->pricingAdjuster, 'applyAttendeePricing'], 999);

        add_filter('woocommerce_product_get_price', [$this->pricingAdjuster, 'filterCartProductPrice'], 9999, 2);
        add_filter('woocommerce_product_get_regular_price', [$this->pricingAdjuster, 'filterCartProductPrice'], 9999, 2);
        add_filter('woocommerce_product_get_sale_price', [$this->pricingAdjuster, 'filterCartProductSalePrice'], 9999, 2);
        add_filter('woocommerce_product_is_on_sale', [$this->pricingAdjuster, 'filterCartProductIsOnSale'], 9999, 2);

        add_filter('woocommerce_add_to_cart_redirect', [$this->pendingCheckoutManager, 'maybeRedirectToCheckout'], 10, 2);
        add_action('init', [$this->pendingCheckoutManager, 'maybeResumePendingCheckout'], 20);

        add_filter('woocommerce_add_to_cart_quantity', [$this, 'enforceSingleQuantity'], 10, 2);
    }

    /**
     * @param mixed $product
     */
    public function filterButtonText(string $text, $product): string
    {
        if (!is_product() || !$product instanceof WC_Product) {
            return $text;
        }

        if (!$product->is_purchasable() || !$product->is_type('simple')) {
            return $text;
        }

        return __('בחירת כרטיסים', 'tapin');
    }

    public function enforceSingleQuantity($qty, $productId)
    {
        if (!empty($_POST['tapin_attendees']) && $this->productGuards->isProductPurchasable((int) $productId)) {
            return 1;
        }

        return $qty;
    }
}
