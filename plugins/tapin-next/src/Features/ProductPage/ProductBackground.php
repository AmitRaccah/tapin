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
        </style>
        <?php
    }
}
