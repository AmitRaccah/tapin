<?php
namespace Tapin\Events\Features;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;

final class PricingOverrides implements Service {
    public function register(): void {
        add_filter('woocommerce_product_get_sale_price', [$this,'salePrice'], 20, 2);
        add_filter('woocommerce_product_get_price',      [$this,'price'], 20, 2);
        add_filter('woocommerce_product_is_on_sale',     [$this,'onSale'], 20, 2);
    }
    public function salePrice($price, $product){
        $w = SaleWindowsRepository::findActive($product->get_id());
        return $w ? (string)$w['price'] : $price;
    }
    public function price($price, $product){
        $w = SaleWindowsRepository::findActive($product->get_id());
        if ($w) return (string)$w['price'];
        $reg = $product->get_regular_price();
        return $reg !== '' ? $reg : $price;
    }
    public function onSale($is_on_sale, $product){
        return $is_on_sale || (bool) SaleWindowsRepository::findActive($product->get_id());
    }
}
