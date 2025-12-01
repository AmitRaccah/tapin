<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\ProductAvailability;
use WC_Product;

final class StickyPurchaseBar implements Service
{
    private const STYLE_HANDLE  = 'tapin-sticky-purchase-bar';
    private const SCRIPT_HANDLE = 'tapin-sticky-purchase-bar';

    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'renderBar']);
    }

    public function enqueueAssets(): void
    {
        if (!$this->isEligibleProduct()) {
            return;
        }

        $assetsDirPath = plugin_dir_path(__FILE__) . 'assets/';
        $assetsDirUrl  = plugin_dir_url(__FILE__) . 'assets/';

        $stylePath = $assetsDirPath . 'sticky-purchase-bar.css';
        $scriptPath = $assetsDirPath . 'sticky-purchase-bar.js';

        if (file_exists($stylePath)) {
            wp_enqueue_style(
                self::STYLE_HANDLE,
                $assetsDirUrl . 'sticky-purchase-bar.css',
                [],
                $this->assetVersion($stylePath)
            );
        }

        if (file_exists($scriptPath)) {
            wp_enqueue_script(
                self::SCRIPT_HANDLE,
                $assetsDirUrl . 'sticky-purchase-bar.js',
                [],
                $this->assetVersion($scriptPath),
                true
            );
        }
    }

    public function renderBar(): void
    {
        if (!$this->isEligibleProduct()) {
            return;
        }
        $product = wc_get_product(get_the_ID());
        $price   = $product instanceof WC_Product ? wc_get_price_to_display($product) : null;

        if ($product instanceof WC_Product && method_exists($product, 'get_currency')) {
            $currency = (string) $product->get_currency();
        } else {
            $currency = get_woocommerce_currency();
        }

        $formattedPrice = '';

        if ($price !== null) {
            $formattedPrice = trim(wp_strip_all_tags(wc_price(
                $price,
                [
                    'currency'           => $currency,
                    'decimals'           => wc_get_price_decimals(),
                    'price_format'       => '%2$s%1$s',
                    'decimal_separator'  => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                ]
            )));
        }

        $primaryText = $formattedPrice !== ''
            ? sprintf(__('החל מ-%s', 'tapin'), $formattedPrice)
            : __('המשיכו לרכישה', 'tapin');

        $noteText = __('כולל עמלת רכישה', 'tapin');
        ?>
        <div id="tapinStickyPurchaseBar" class="tapin-sticky-bar" hidden>
            <div class="tapin-sticky-bar__content">
                <button type="button" class="tapin-sticky-bar__buy" data-role="submit">
                    <span class="tapin-sticky-bar__buy-main">
                        <?php echo esc_html($primaryText); ?>
                    </span>
                    <span class="tapin-sticky-bar__buy-note"><?php echo esc_html($noteText); ?></span>
                </button>
            </div>
        </div>
        <?php
    }

    private function isEligibleProduct(): bool
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

    private function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }
}
