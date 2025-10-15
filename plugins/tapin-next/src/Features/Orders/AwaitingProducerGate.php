<?php
namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class AwaitingProducerGate implements Service {
    public function register(): void {
        add_action('woocommerce_checkout_order_processed', [$this,'onCheckout'], 9999);
        add_filter('woocommerce_payment_complete_order_status', [$this,'forceAwaiting'], 10, 3);
        add_filter('woocommerce_cod_process_payment_order_status', [$this,'forceAwaitingCod'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this,'revertIfNeeded'], 5, 4);
        add_filter('woocommerce_payment_complete_reduce_order_stock', [$this,'noStockReduction'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this,'suppressProcessingEmail'], 10, 2);
        add_action('woocommerce_thankyou', [$this,'thankyouNote']);
    }

    private function collectProducerIds(\WC_Order $order): array {
        $ids = [];
        foreach ($order->get_items('line_item') as $item) {
            $pid = $item->get_product_id(); if(!$pid) continue;
            $author = (int)get_post_field('post_author', $pid);
            if ($author) $ids[] = $author;
        }
        return array_values(array_unique($ids));
    }

    public function onCheckout($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $producers = $this->collectProducerIds($order);
        if (!$producers) return;
        $order->update_meta_data('_tapin_producer_ids', $producers);
        if ('awaiting-producer' !== $order->get_status()) {
            $order->set_status('awaiting-producer', 'הוזז לסטטוס ממתין לאישור מפיק.');
        }
        $order->save();
    }

    public function forceAwaiting($status, $order_id, $order){
        if ($order && $order->get_meta('_tapin_producer_ids')) return 'awaiting-producer';
        return $status;
    }
    public function forceAwaitingCod($status, $order){
        if ($order && $order->get_meta('_tapin_producer_ids')) return 'awaiting-producer';
        return $status;
    }

    public function revertIfNeeded($order_id, $from, $to, $order){
        static $guard=false;
        if ($guard || !$order) return;
        if (!$order->get_meta('_tapin_producer_ids')) return;
        if ($order->get_meta('_tapin_producer_approved')) return;
        if ($to !== 'awaiting-producer') {
            $guard=true;
            $order->update_status('awaiting-producer', 'Gate: reverted to awaiting-producer until producer approves.');
            $guard=false;
        }
    }

    public function noStockReduction($reduce, $order_id){
        $o = wc_get_order($order_id);
        if ($o && 'awaiting-producer' === $o->get_status()) return false;
        return $reduce;
    }
    public function suppressProcessingEmail($enabled, $order){
        if ($order && $order->has_status('awaiting-producer')) return false;
        return $enabled;
    }
    public function thankyouNote($order_id){
        $o = wc_get_order($order_id);
        if ($o && $o->has_status('awaiting-producer')) {
            echo '<p class="woocommerce-info" style="direction:rtl;text-align:right">ההזמנה התקבלה וממתינה לאישור המפיק. לא בוצעה גבייה/סליקה.</p>';
        }
    }

    /** Capture & mark as approved; fallback to order note if no gateway hook */
    public static function captureAndApprove(\WC_Order $order): bool {
        $did=false;
        $pm = $order->get_payment_method();
        if ($pm && false !== strpos($pm, 'wcpay') && has_action('woocommerce_order_action_wcpay_capture_charge')) {
            do_action('woocommerce_order_action_wcpay_capture_charge', $order); $did=true;
        }
        if (!$did && $pm && false !== strpos($pm, 'stripe') && has_action('woocommerce_order_action_stripe_capture_charge')) {
            do_action('woocommerce_order_action_stripe_capture_charge', $order); $did=true;
        }
        if (!$did) $order->add_order_note('אושר ע״י מפיק. אם נדרש – בצעו Capture ידני ב־Order actions.');

        $order->update_meta_data('_tapin_producer_approved', 1);
        $all_virtual = true;
        foreach ($order->get_items('line_item') as $it){
            $p = $it->get_product();
            if (!$p || !$p->is_virtual()) { $all_virtual=false; break; }
        }
        $order->update_status($all_virtual?'completed':'processing', 'אושר על ידי המפיק.');
        $order->save();
        return $did;
    }
}
