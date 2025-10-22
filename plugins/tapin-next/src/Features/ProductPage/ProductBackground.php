<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;

final class ProductBackground implements Service
{
    private string $backgroundUrl = '';

    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_action('wp', [$this, 'maybeSetup']);
    }

    public function maybeSetup(): void
    {
        if (!function_exists('is_product') || !function_exists('wc_get_product') || !is_product()) {
            return;
        }

        $productId = get_the_ID();
        if (!$productId) {
            return;
        }

        $this->backgroundUrl = $this->resolveBackgroundUrl((int) $productId);
        if ($this->backgroundUrl === '') {
            return;
        }

        add_filter('body_class', [$this, 'addBodyClass']);
        add_action('wp_head', [$this, 'renderStyles'], 30);
    }

    /**
     * @param int $productId
     */
    private function resolveBackgroundUrl(int $productId): string
    {
        $attachmentId = (int) get_post_meta($productId, MetaKeys::EVENT_BG_IMAGE, true);
        if ($attachmentId <= 0) {
            return '';
        }

        $image = wp_get_attachment_image_src($attachmentId, 'full');
        if (!is_array($image) || empty($image[0])) {
            return '';
        }

        return (string) $image[0];
    }

    /**
     * @param array<int,string> $classes
     * @return array<int,string>
     */
    public function addBodyClass(array $classes): array
    {
        if ($this->backgroundUrl !== '' && !in_array('tapin-product-has-background', $classes, true)) {
            $classes[] = 'tapin-product-has-background';
        }

        return $classes;
    }

    public function renderStyles(): void
    {
        if ($this->backgroundUrl === '') {
            return;
        }

        $url = esc_url($this->backgroundUrl);
        ?>
        <style id="tapin-product-background">
            body.single-product.tapin-product-has-background #Content {
                position: relative;
                z-index: 0;
                background: none !important;
                min-height: 100vh;
            }
            body.single-product.tapin-product-has-background #Content::before {
                content: "";
                position: absolute;
                inset: 0;
                background-image: url('<?php echo $url; ?>');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-attachment: fixed;
                image-rendering: auto;
                pointer-events: none;
                transform: translateZ(0);
                z-index: -1;
            }
            @media (max-width: 1024px) {
                body.single-product.tapin-product-has-background #Content::before {
                    background-attachment: scroll;
                }
            }
            body.single-product.tapin-product-has-background #Content .content_wrapper {
                padding-top: clamp(40px, 6vw, 96px);
                padding-bottom: clamp(40px, 6vw, 96px);
            }
            body.single-product.tapin-product-has-background .entry-summary {
                margin-top: clamp(16px, 3vw, 32px);
            }
            body.single-product.tapin-product-has-background .entry-summary .mcb-column-inner,
            body.single-product.tapin-product-has-background .jq-tabs {
                position: relative;
                z-index: 1;
                background: rgba(8, 10, 22, 0.78);
                border: 1px solid rgba(255, 255, 255, 0.14);
                border-radius: clamp(16px, 2.5vw, 28px);
                padding: clamp(20px, 3vw, 36px);
                color: #f5f7ff;
                box-shadow: 0 30px 60px rgba(5, 9, 20, 0.45);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }
            body.single-product.tapin-product-has-background .entry-summary .mcb-column-inner > *:not(:last-child) {
                margin-bottom: clamp(12px, 2vw, 24px);
            }
            body.single-product.tapin-product-has-background .entry-summary h1,
            body.single-product.tapin-product-has-background .entry-summary .price,
            body.single-product.tapin-product-has-background .entry-summary .stock {
                color: #ffffff;
                text-shadow: 0 4px 22px rgba(0, 0, 0, 0.5);
            }
            body.single-product.tapin-product-has-background .entry-summary a {
                color: #ffffff;
            }
            body.single-product.tapin-product-has-background .entry-summary p,
            body.single-product.tapin-product-has-background .entry-summary label,
            body.single-product.tapin-product-has-background .entry-summary span,
            body.single-product.tapin-product-has-background .entry-summary .woocommerce-Price-amount,
            body.single-product.tapin-product-has-background .entry-summary .woocommerce-Price-currencySymbol {
                color: inherit;
            }
            body.single-product.tapin-product-has-background .entry-summary i,
            body.single-product.tapin-product-has-background .jq-tabs i {
                color: inherit;
            }
            body.single-product.tapin-product-has-background .entry-summary .product_meta,
            body.single-product.tapin-product-has-background .entry-summary .share-simple-wrapper {
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                padding-top: clamp(12px, 2vw, 20px);
            }
            body.single-product.tapin-product-has-background .jq-tabs {
                margin-top: clamp(24px, 4vw, 48px);
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav {
                background: transparent;
                border: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                margin: 0 0 clamp(16px, 2.5vw, 28px);
                padding: 0;
                list-style: none;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li {
                float: none;
                display: inline-flex;
                align-items: center;
                margin: 0 clamp(12px, 2vw, 18px) 0 0;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li:last-child {
                margin-right: 0;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.35em;
                padding: 0.55em 1.35em;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.08);
                color: inherit;
                font-weight: 600;
                letter-spacing: 0.02em;
                transition: background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li a:hover,
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li a:focus {
                background: rgba(255, 255, 255, 0.18);
                color: #ffffff;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li.ui-tabs-active a {
                background: rgba(255, 255, 255, 0.3);
                color: #0f1627;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-panel {
                background: transparent;
                padding: 0;
                color: inherit;
            }
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-panel p,
            body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-panel li {
                color: inherit;
            }
            body.single-product.tapin-product-has-background .jq-tabs h2,
            body.single-product.tapin-product-has-background .jq-tabs h3,
            body.single-product.tapin-product-has-background .jq-tabs h4 {
                color: inherit;
            }
            @media (max-width: 768px) {
                body.single-product.tapin-product-has-background .entry-summary {
                    margin-top: clamp(24px, 5vw, 40px);
                }
                body.single-product.tapin-product-has-background .entry-summary .mcb-column-inner,
                body.single-product.tapin-product-has-background .jq-tabs {
                    padding: clamp(18px, 5vw, 28px);
                }
                body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li {
                    margin: 0 0 clamp(10px, 3vw, 16px) 0;
                    width: 100%;
                }
                body.single-product.tapin-product-has-background .jq-tabs .ui-tabs-nav li a {
                    width: 100%;
                }
            }
        </style>
        <?php
    }
}
