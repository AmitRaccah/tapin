<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Support\PaymentGatewayHelper;
use Tapin\Events\Support\Orders;
use Tapin\Events\Support\TicketSalesCounter;
use WC_Order;
use WC_Email_Customer_Processing_Order;

final class AwaitingProducerGate implements Service
{
    private const LEGACY_PRODUCER_ID = 0;

    private bool $statusGuard = false;

    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'onCheckout'], 9999, 1);
        add_filter('woocommerce_payment_complete_order_status', [$this, 'forceAwaiting'], 10, 3);
        add_filter('woocommerce_cod_process_payment_order_status', [$this, 'forceAwaiting'], 10, 2);
        add_action('woocommerce_order_status_changed', [$this, 'revertIfNeeded'], 5, 4);
        add_action('woocommerce_order_status_changed', [$this, 'releaseTicketSalesOnClose'], 12, 4);
        add_filter('woocommerce_payment_complete_reduce_order_stock', [$this, 'preventStockReduction'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'suppressProcessingEmail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'suppressCompletedEmail'], 10, 2);
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
            $order->set_status($awaiting, 'ההזמנה הועברה לסטטוס "ממתין לאישור מפיק".');
        }

        $order->save();

        foreach ($producerIds as $producerId) {
            $producerId = (int) $producerId;
            if ($producerId <= 0) {
                continue;
            }

            do_action('tapin/events/order/awaiting_producer', $order, $producerId);
        }
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
        if ($to === $awaiting) {
            return;
        }
        if ($to === PartiallyApprovedStatus::STATUS_SLUG) {
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
        if ($order instanceof WC_Order && $order->get_status() === self::awaitingStatusSlug()) {
            return false;
        }

        return $reduce;
    }

    public function suppressProcessingEmail(bool $enabled, ?WC_Order $order): bool
    {
        if (!$order instanceof WC_Order) {
            return $enabled;
        }

        $status = $order->get_status();
        if (in_array($status, [self::awaitingStatusSlug(), PartiallyApprovedStatus::STATUS_SLUG], true)) {
            return false;
        }

        return $enabled;
    }

    public function suppressCompletedEmail(bool $enabled, ?WC_Order $order): bool
    {
        if (!$order instanceof WC_Order) {
            return $enabled;
        }

        $producerIds = Orders::collectProducerIds($order);
        $status      = $order->get_status();

        if ($producerIds !== [] && in_array($status, [self::awaitingStatusSlug(), PartiallyApprovedStatus::STATUS_SLUG], true)) {
            return false;
        }

        return $enabled;
    }

    public function maybeSendWooProcessingEmail(int $orderId, WC_Order $order): void
    {
        if (!function_exists('WC')) {
            return;
        }

        try {
            $mailer = WC()->mailer();
        } catch (\Throwable $e) {
            return;
        }

        if (!is_object($mailer) || !method_exists($mailer, 'get_emails')) {
            return;
        }

        $emails = $mailer->get_emails();
        if (!is_array($emails) || !isset($emails['WC_Email_Customer_Processing_Order'])) {
            return;
        }

        $email = $emails['WC_Email_Customer_Processing_Order'];
        if (!$email instanceof WC_Email_Customer_Processing_Order) {
            return;
        }

        $email->trigger($orderId, $order);
    }

    public function thankyouNotice(int $orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order && $order->has_status(self::awaitingStatusSlug())) {
            echo '<p class="woocommerce-info" style="direction:rtl;text-align:right">ההזמנה ממתינה לאישור המפיק. נעדכן אותך לאחר האישור.</p>';
        }
    }

    public static function captureAndApprove(WC_Order $order, ?int $producerId = null, ?float $captureAmount = null): bool
    {
        $producerKey = $producerId !== null && $producerId > 0 ? $producerId : null;
        $capturedTotals = self::normalizeProducerFloatMap($order->get_meta('_tapin_partial_captured_total', true), $producerKey);
        $alreadyCaptured = $producerKey !== null
            ? ($capturedTotals[$producerKey] ?? ($capturedTotals[self::LEGACY_PRODUCER_ID] ?? 0.0))
            : array_sum($capturedTotals);
        $globalCaptured = array_sum($capturedTotals);

        $orderTotal      = (float) $order->get_total();
        $resolvedTarget  = $captureAmount !== null ? max(0.0, (float) $captureAmount) : $orderTotal;
        if ($producerKey !== null && $captureAmount === null) {
            $producerTotal = self::resolveProducerApprovedTotal($order, $producerKey);
            if ($producerTotal > 0.0) {
                $resolvedTarget = min($resolvedTarget, $producerTotal);
            }
        }

        $intendedAmount    = min($resolvedTarget, $orderTotal);
        $producerRemaining = $producerKey !== null
            ? max(0.0, $intendedAmount - $alreadyCaptured)
            : max(0.0, $intendedAmount - $globalCaptured);
        $remainingGlobal   = max(0.0, $orderTotal - $globalCaptured);
        $toCapture         = min($producerRemaining, $remainingGlobal);

        $didCapture = true;
        if ($toCapture > 0.0) {
            $didCapture = PaymentGatewayHelper::capture($order, $toCapture);
            if ($didCapture) {
                if ($producerKey !== null) {
                    $capturedTotals[$producerKey] = ($capturedTotals[$producerKey] ?? 0.0) + $toCapture;
                    self::saveProducerFloatMap($order, '_tapin_partial_captured_total', $capturedTotals);
                } else {
                    $order->update_meta_data('_tapin_partial_captured_total', $alreadyCaptured + $toCapture);
                }
            }
        }

        if (!$didCapture) {
            $order->add_order_note(__('Payment capture was not completed automatically. Please review the order actions.', 'tapin'));
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
            __('Producer approved the order.', 'tapin')
        );
        if ($didCapture && $captureAmount === null) {
            if ($producerKey !== null) {
                unset($capturedTotals[$producerKey], $capturedTotals[self::LEGACY_PRODUCER_ID]);
                self::saveProducerFloatMap($order, '_tapin_partial_captured_total', $capturedTotals);
            } else {
                $order->delete_meta_data('_tapin_partial_captured_total');
            }
        }
        $order->save();

        $producerIds = $producerKey !== null ? [$producerKey] : self::ensureProducerMeta($order);
        foreach ($producerIds as $producer) {
            $pid = (int) $producer;
            if ($pid <= 0) {
                continue;
            }

            do_action('tapin/events/order/approved_by_producer', $order, $pid);
        }

        return $didCapture;
    }

    public function releaseTicketSalesOnClose(int $orderId, string $from, string $to, ?WC_Order $order): void
    {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($orderId);
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $inactive = ['cancelled', 'refunded', 'failed'];
        if (!in_array($to, $inactive, true)) {
            return;
        }

        $rawRecorded = $order->get_meta('_tapin_ticket_sales_recorded', true);
        if (!is_array($rawRecorded) || $rawRecorded === []) {
            return;
        }

        $recorded = $this->normalizeSalesMap($rawRecorded);
        if ($recorded === []) {
            $order->delete_meta_data('_tapin_ticket_sales_recorded');
            $order->save();
            return;
        }

        foreach ($recorded as $productId => $types) {
            $delta = [];
            foreach ($types as $typeId => $count) {
                $qty = max(0, (int) $count);
                if ($qty > 0) {
                    $delta[$typeId] = -1 * $qty;
                }
            }

            if ($delta !== []) {
                TicketSalesCounter::adjust((int) $productId, $delta);
            }
        }

        $order->delete_meta_data('_tapin_ticket_sales_recorded');
        $order->save();
    }

    private static function resolveProducerApprovedTotal(WC_Order $order, int $producerId): float
    {
        $totals = self::normalizeProducerFloatMap($order->get_meta('_tapin_partial_approved_total', true), $producerId);
        if (isset($totals[$producerId])) {
            return $totals[$producerId];
        }

        if (isset($totals[self::LEGACY_PRODUCER_ID])) {
            return $totals[self::LEGACY_PRODUCER_ID];
        }

        if ($totals !== []) {
            return array_sum($totals);
        }

        return 0.0;
    }

    /**
     * @param mixed $raw
     * @return array<int,float>
     */
    private static function normalizeProducerFloatMap($raw, ?int $producerId = null): array
    {
        $result = [];
        if (is_array($raw)) {
            foreach ($raw as $producerKey => $value) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }

                if (is_array($value)) {
                    continue;
                }

                $floatVal = max(0.0, (float) $value);
                if ($floatVal > 0.0) {
                    $result[$pid] = $floatVal;
                }
            }
        }

        if ($result !== []) {
            return $result;
        }

        if (is_numeric($raw)) {
            $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;
            $val    = max(0.0, (float) $raw);
            if ($val > 0.0) {
                $result[$target] = $val;
            }
        }

        return $result;
    }

    private static function saveProducerFloatMap(WC_Order $order, string $key, array $map): void
    {
        $clean = [];
        foreach ($map as $producerId => $value) {
            $pid = (int) $producerId;
            if ($pid <= 0) {
                $pid = self::LEGACY_PRODUCER_ID;
            }

            $floatVal = max(0.0, (float) $value);
            if ($floatVal <= 0.0) {
                continue;
            }
            $clean[$pid] = $floatVal;
        }

        if ($clean === []) {
            $order->delete_meta_data($key);
            return;
        }

        $order->update_meta_data($key, $clean);
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

    /**
     * @param mixed $raw
     * @return array<int,array<string,int>>
     */
    private function normalizeSalesMap($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $productId => $types) {
            $pid = (int) $productId;
            if ($pid <= 0 || !is_array($types)) {
                continue;
            }

            foreach ($types as $typeId => $count) {
                $tid = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
                $qty = max(0, (int) $count);

                if ($tid === '' || $qty <= 0) {
                    continue;
                }

                $result[$pid][$tid] = $qty;
            }
        }

        return $result;
    }
}
