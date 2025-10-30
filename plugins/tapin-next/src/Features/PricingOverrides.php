<?php
namespace Tapin\Events\Features;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;

final class PricingOverrides implements Service {
    public function register(): void {
        add_filter('woocommerce_product_get_sale_price', [$this,'salePrice'], 20, 2);
        add_filter('woocommerce_product_get_price',      [$this,'price'], 20, 2);
        add_filter('woocommerce_product_is_on_sale',     [$this,'onSale'], 20, 2);
    }

    public function salePrice($price, $product){
        $resolved = $this->resolveActivePrice($product);
        return $resolved !== null ? $resolved : $price;
    }

    public function price($price, $product){
        $resolved = $this->resolveActivePrice($product);
        if ($resolved !== null) {
            return $resolved;
        }

        $base = $this->resolveBasePrice($product);
        if ($base !== null) {
            return $base;
        }

        $reg = $product && method_exists($product, 'get_regular_price') ? $product->get_regular_price() : '';
        return $reg !== '' ? $reg : $price;
    }

    public function onSale($is_on_sale, $product){
        if ($is_on_sale) {
            return true;
        }

        $productId = $product && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
        if (!$productId) {
            return false;
        }

        $types  = TicketTypesRepository::get($productId);
        $window = SaleWindowsRepository::findActive($productId, $types);

        return $window !== null && isset($window['price']) && (float) $window['price'] > 0;
    }

    private function resolveActivePrice($product): ?string
    {
        $productId = $product && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
        if (!$productId) {
            return null;
        }

        $types  = TicketTypesRepository::get($productId);
        $window = SaleWindowsRepository::findActive($productId, $types);
        if (!$window || !isset($window['price']) || (float) $window['price'] <= 0) {
            return null;
        }

        return $this->formatPrice((float) $window['price']);
    }

    private function resolveBasePrice($product): ?string
    {
        $productId = $product && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
        if (!$productId) {
            return null;
        }

        $types = TicketTypesRepository::get($productId);
        $prices = [];
        foreach ($types as $type) {
            if (!is_array($type)) {
                continue;
            }
            if (!isset($type['base_price']) || (float) $type['base_price'] <= 0) {
                continue;
            }
            $prices[] = (float) $type['base_price'];
        }

        if ($prices === []) {
            return null;
        }

        return $this->formatPrice((float) min($prices));
    }

    private function formatPrice(float $value): string
    {
        if (function_exists('wc_format_decimal')) {
            return (string) wc_format_decimal($value);
        }
        return (string) $value;
    }
}
