<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Guards;

use Tapin\Events\Support\ProductAvailability;
use WC_Product;

final class ProductGuards
{
    public function isEligibleProduct(): bool
    {
        if (!function_exists('is_product') || !function_exists('wc_get_product') || !is_product()) {
            return false;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
            return false;
        }

        if (!$product->is_type('simple')) {
            return false;
        }

        if (!$product->is_purchasable()) {
            return false;
        }

        return ProductAvailability::isCurrentlyPurchasable((int) $product->get_id());
    }

    public function shouldHandleProduct(int $productId): bool
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            return false;
        }

        if (!$product->is_type('simple')) {
            return false;
        }

        if (!$product->is_purchasable()) {
            return false;
        }

        return ProductAvailability::isCurrentlyPurchasable($productId);
    }

    public function isProductPurchasable(int $productId): bool
    {
        return ProductAvailability::isCurrentlyPurchasable($productId);
    }
}
