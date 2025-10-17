<?php
namespace Tapin\Events\Features;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class PurchasableGate implements Service {
    public function register(): void {
        add_filter('woocommerce_is_purchasable', [$this, 'gate'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this, 'notice'], 6);
    }

    public function gate($purchasable, $product) {
        if (!$product) {
            return $purchasable;
        }
        $pid    = $product->get_id();
        $paused = get_post_meta($pid, MetaKeys::PAUSED, true);
        if ($paused === 'yes') {
            return false;
        }

        $eventTs = Time::productEventTs($pid);
        if ($eventTs && $eventTs <= time()) {
            return false;
        }

        return $purchasable;
    }

    public function notice(): void {
        global $product;
        if (!$product) {
            return;
        }
        $pid    = $product->get_id();
        $paused = get_post_meta($pid, MetaKeys::PAUSED, true);
        $event  = Time::productEventTs($pid);

        if ($paused === 'yes') {
            echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">האירוע הושעה זמנית ולכן לא ניתן לרכישה.</div>';
        } elseif ($event && $event <= time()) {
            echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">האירוע כבר הסתיים ולכן אינו זמין לרכישה.</div>';
        }
    }
}
