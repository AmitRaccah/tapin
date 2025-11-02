<?php
namespace Tapin\Events\Features;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\ProductAvailability;
use Tapin\Events\Support\Time;

final class PurchasableGate implements Service {
    public function register(): void {
        add_filter('woocommerce_is_purchasable', [$this, 'gate'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this, 'notice'], 6);
    }

    public function gate($purchasable, $product) {
        if (!$product || !method_exists($product, 'get_id')) {
            return $purchasable;
        }

        $productId = (int) $product->get_id();
        if ($productId <= 0) {
            return $purchasable;
        }

        if ($this->isPaused($productId) || $this->eventHasEnded($productId)) {
            return false;
        }

        $availability = ProductAvailability::status($productId);
        if (!$availability['is_purchasable']) {
            return false;
        }

        return $purchasable;
    }

    public function notice(): void {
        global $product;
        if (!$product || !method_exists($product, 'get_id')) {
            return;
        }

        $productId = (int) $product->get_id();
        if ($productId <= 0) {
            return;
        }

        if ($this->isPaused($productId)) {
            echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">?"???T?"??� ?"??c?�?" ?-???�?T?x ????>?? ???? ?�?T?x?? ???"?>?T?c".</div>';
            return;
        }

        if ($this->eventHasEnded($productId)) {
            echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">?"???T?"??� ?>?`?" ?"?�?x?T?T?? ????>?? ???T?�? ?-???T?? ???"?>?T?c".</div>';
            return;
        }

        $availability = ProductAvailability::status($productId);
        if ($availability['is_purchasable']) {
            return;
        }

        $message = $this->availabilityNoticeMessage($availability);
        if ($message === '') {
            return;
        }

        echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">' . esc_html($message) . '</div>';
    }

    private function isPaused(int $productId): bool {
        return get_post_meta($productId, MetaKeys::PAUSED, true) === 'yes';
    }

    private function eventHasEnded(int $productId): bool {
        $eventTs = Time::productEventTs($productId);
        return $eventTs && $eventTs <= time();
    }

    /**
     * @param array{has_windows:bool,sale_state:string,has_tickets:bool,is_purchasable:bool} $availability
     */
    private function availabilityNoticeMessage(array $availability): string {
        if (!$availability['has_tickets']) {
            return __('This event is sold out.', 'tapin');
        }

        if ($availability['sale_state'] === 'upcoming') {
            return __('Ticket sales have not opened yet.', 'tapin');
        }

        if ($availability['sale_state'] === 'ended') {
            return __('Ticket sales for this event have ended.', 'tapin');
        }

        return '';
    }
}

