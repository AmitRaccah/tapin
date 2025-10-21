<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
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
        ?>
        <div id="tapinStickyPurchaseBar" class="tapin-sticky-bar" hidden>
            <div class="tapin-sticky-bar__content">
                <div class="tapin-sticky-bar__quantity" dir="rtl">
                    <span
                        class="tapin-sticky-bar__label"
                        data-role="label"
                        data-singular="כרטיס"
                        data-plural="כרטיסים"
                    >כרטיסים</span>
                    <button
                        type="button"
                        class="tapin-sticky-bar__qty-btn"
                        data-action="decrease"
                        aria-label="פחות כרטיס"
                    >-</button>
                    <span class="tapin-sticky-bar__qty-value" data-role="quantity" aria-live="polite" aria-atomic="true">1</span>
                    <button
                        type="button"
                        class="tapin-sticky-bar__qty-btn"
                        data-action="increase"
                        aria-label="עוד כרטיס"
                    >+</button>
                </div>
                <button type="button" class="tapin-sticky-bar__buy" data-role="submit">לקנייה</button>
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

        return $product->is_purchasable() && $product->is_type('simple');
    }

    private function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }
}
