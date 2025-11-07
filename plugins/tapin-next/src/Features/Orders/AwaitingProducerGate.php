<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\Orders;
use WC_Order;

final class AwaitingProducerGate implements Service
{
    private bool $statusGuard = false;

    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'onCheckout'], 9999, 1);
        add_filter('woocommerce_payment_complete_order_status', [$this, 'forceAwaiting'], 10, 3);
        add_filter('woocommerce_cod_process_payment_order_status', [$this, 'forceAwaiting'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this, 'revertIfNeeded'], 5, 4);
        add_filter('woocommerce_payment_complete_reduce_order_stock', [$this, 'preventStockReduction'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'suppressProcessingEmail'], 10, 2);
        add_action('woocommerce_thankyou', [$this, 'thankyouNotice'], 10, 1);
    }

    public function onCheckout(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }

        $producerIds = Orders::collectProducerIds($order);
        if ($producerIds === []) {
            return;
        }

        $order->update_meta_data('_tapin_producer_ids', $producerIds);

        $awaiting = self::awaitingStatusSlug();
        if ($order->get_status() !== $awaiting) {
            $order->set_status($awaiting, 'הוזז לסטטוס ' . AwaitingProducerStatus::STATUS_LABEL . '.');
        }

        $order->save();
    }

    /**
     * @param int|WC_Order $orderOrId
     */
    public function forceAwaiting(string $status, $orderOrId, ?WC_Order $order = null): string
    {
        if (!$order instanceof WC_Order) {
            if ($orderOrId instanceof WC_Order) {
                $order = $orderOrId;
            } elseif (is_numeric($orderOrId) && function_exists('wc_get_order')) {
                $order = wc_get_order((int) $orderOrId);
            }
        }

        if ($order instanceof WC_Order) {
            $producerIds = self::ensureProducerMeta($order);
            if ($producerIds !== []) {
                return self::awaitingStatusSlug();
            }
        }

        return $status;
    }

    public function revertIfNeeded(int $orderId, string $from, string $to, ?WC_Order $order): void
    {
        if ($this->statusGuard || !$order instanceof WC_Order) {
            return;
        }

        $producerIds = self::ensureProducerMeta($order);
        if ($producerIds === []) {
            return;
        }

        if ($order->get_meta('_tapin_producer_approved')) {
            return;
        }

        $awaiting = self::awaitingStatusSlug();
        if (in_array($to, ['cancelled', 'refunded', 'failed'], true)) {
            return;
        }
        if ($to === $awaiting || $to === PartialApprovalStatus::STATUS_SLUG) {
            return;
        }

        $this->statusGuard = true;
        $order->update_status($awaiting, 'Gate: reverted to awaiting-producer until producer approves.');
        $this->statusGuard = false;
    }

    public function preventStockReduction(bool $reduce, int $orderId): bool
    {
        if (!function_exists('wc_get_order')) {
            return $reduce;
        }

        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order && $order->has_status([self::awaitingStatusSlug(), PartialApprovalStatus::STATUS_SLUG])) {
            return false;
        }

        return $reduce;
    }

    public function suppressProcessingEmail(bool $enabled, ?WC_Order $order): bool
    {
        if ($order instanceof WC_Order && $order->has_status([self::awaitingStatusSlug(), PartialApprovalStatus::STATUS_SLUG])) {
            return false;
        }

        return $enabled;
    }

    public function thankyouNotice(int $orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order && $order->has_status(self::awaitingStatusSlug())) {
            echo '<p class="woocommerce-info" style="direction:rtl;text-align:right">ההזמנה ממתינה לאישור מפיק. נעדכן אותך ברגע שתאושר.</p>';
        }
    }

    public static function captureAndApprove(WC_Order $order): bool
    {
        $didCapture = false;
        $paymentMethod = $order->get_payment_method();

        if ($paymentMethod && strpos($paymentMethod, 'wcpay') !== false && has_action('woocommerce_order_action_wcpay_capture_charge')) {
            do_action('woocommerce_order_action_wcpay_capture_charge', $order);
            $didCapture = true;
        }

        if (!$didCapture && $paymentMethod && strpos($paymentMethod, 'stripe') !== false && has_action('woocommerce_order_action_stripe_capture_charge')) {
            do_action('woocommerce_order_action_stripe_capture_charge', $order);
            $didCapture = true;
        }

        if (!$didCapture) {
            $order->add_order_note('תפיסת תשלום: לא בוצעה אוטומטית, ניתן לבצע ידנית מתוך Order actions.');
        }

        $order->update_meta_data('_tapin_producer_approved', 1);

        $allVirtual = true;
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product || !$product->is_virtual()) {
                $allVirtual = false;
                break;
            }
        }

        $order->update_status(
            $allVirtual ? 'completed' : 'processing',
            'ההזמנה אושרה על ידי המפיק.'
        );
        $order->save();

        return $didCapture;
    }

    private static function awaitingStatusSlug(): string
    {
        static $slug = null;

        if ($slug !== null) {
            return $slug;
        }

        $slug = AwaitingProducerStatus::STATUS_SLUG;

        if (function_exists('wc_get_order_statuses')) {
            foreach (wc_get_order_statuses() as $key => $label) {
                $normalized = str_replace('wc-', '', (string) $key);
                if ($normalized === AwaitingProducerStatus::STATUS_SLUG) {
                    $slug = $normalized;
                    break;
                }

                if (stripos((string) $label, AwaitingProducerStatus::STATUS_LABEL) !== false) {
                    $slug = $normalized;
                }
            }
        }

        return $slug;
    }

    /**
     * @return array<int,int>
     */
    private static function ensureProducerMeta(WC_Order $order): array
    {
        $current = (array) $order->get_meta('_tapin_producer_ids', true);
        $current = array_values(array_filter(array_map('intval', $current)));
        if ($current !== []) {
            return $current;
        }

        $ids = Orders::collectProducerIds($order);
        if ($ids === []) {
            return [];
        }

        $order->update_meta_data('_tapin_producer_ids', $ids);
        return $ids;
    }
}
