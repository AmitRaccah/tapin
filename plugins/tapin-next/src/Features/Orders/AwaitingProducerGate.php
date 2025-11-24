<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Features\Orders\TicketEmails\TicketTokensRepository;
use Tapin\Events\Support\PaymentGatewayHelper;
use Tapin\Events\Support\Orders;
use Tapin\Events\Support\TicketSalesCounter;
use WC_Order;
use WC_Email_Customer_Processing_Order;
use WC_Order_Item_Product;
use WC_Product;

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

        if (self::allProducersApproved($order)) {
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
        if ($producerKey !== null) {
            $alreadyCaptured = $capturedTotals[$producerKey] ?? ($capturedTotals[self::LEGACY_PRODUCER_ID] ?? 0.0);
        } else {
            $alreadyCaptured = array_sum($capturedTotals);
        }
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
            return false;
        }

        $allVirtual = true;
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product || !$product->is_virtual()) {
                $allVirtual = false;
                break;
            }
        }

        $allApproved = self::allProducersApproved($order);
        if ($allApproved) {
            $order->update_meta_data('_tapin_producer_approved', 1);
            $order->update_status(
                $allVirtual ? 'completed' : 'processing',
                __('Producer approved the order.', 'tapin')
            );
            if ($captureAmount === null) {
                $order->delete_meta_data('_tapin_partial_captured_total');
            }
        } else {
            $order->delete_meta_data('_tapin_producer_approved');
            if (!$order->has_status(PartiallyApprovedStatus::STATUS_SLUG)) {
                $order->update_status(
                    PartiallyApprovedStatus::STATUS_SLUG,
                    __('Producer approval recorded; awaiting other producers.', 'tapin')
                );
            }
        }
        $order->save();

        $producerIds = $producerKey !== null ? [$producerKey] : self::ensureProducerMeta($order);
        foreach ($producerIds as $producer) {
            $pid = (int) $producer;
            if ($pid <= 0) {
                continue;
            }

            do_action('tapin/events/order/producer_attendees_approved', $order, $pid);
        }

        $finalProducers = $allApproved ? self::ensureProducerMeta($order) : $producerIds;
        foreach ($finalProducers as $producer) {
            $pid = (int) $producer;
            if ($pid <= 0) {
                continue;
            }

            do_action('tapin/events/order/approved_by_producer', $order, $pid);
        }

        return true;
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
            $order->delete_meta_data('_tapin_ticket_sales_recorded');
        }

        if (is_array($rawRecorded) && $rawRecorded !== []) {
            $recorded = $this->normalizeSalesMap($rawRecorded);
            if ($recorded === []) {
                $order->delete_meta_data('_tapin_ticket_sales_recorded');
            } else {
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
            }
        }

        $order->delete_meta_data('_tapin_partial_captured_total');
        $order->delete_meta_data('_tapin_partial_approved_map');
        $order->delete_meta_data('_tapin_partial_approved_total');
        $order->delete_meta_data('_tapin_producer_approved_attendees');

        $tokens = new TicketTokensRepository();
        $tokens->invalidateTokensForOrder($order);
        $order->delete_meta_data('_tapin_ticket_emails_sent');
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

    public static function allProducersApproved(WC_Order $order): bool
    {
        $producerIds = self::ensureProducerMeta($order);
        if ($producerIds === []) {
            return false;
        }

        foreach ($producerIds as $producerId) {
            $pid = (int) $producerId;
            if ($pid <= 0) {
                continue;
            }

            if (!self::isProducerApproved($order, $pid)) {
                return false;
            }
        }

        return true;
    }

    private static function isProducerApproved(WC_Order $order, int $producerId): bool
    {
        $approvedByProducer = self::normalizeApprovedMetaByProducer(
            $order->get_meta('_tapin_producer_approved_attendees', true),
            $producerId
        );
        $map = $approvedByProducer[$producerId] ?? $approvedByProducer[self::LEGACY_PRODUCER_ID] ?? [];
        if ($map === [] && $approvedByProducer !== []) {
            $first = reset($approvedByProducer);
            if (is_array($first)) {
                $map = $first;
            }
        }

        $hasItems = false;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product || !self::isProducerLineItem($item, $producerId)) {
                continue;
            }

            $hasItems = true;
            $itemId   = (int) $item->get_id();
            $quantity = max(0, (int) $item->get_quantity());
            $approved = isset($map[$itemId]) ? self::filterIndices((array) $map[$itemId]) : [];

            if ($quantity > 0 && count($approved) < $quantity) {
                return false;
            }
        }

        if (!$hasItems) {
            return true;
        }

        return true;
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,array<int,int>>>
     */
    private static function normalizeApprovedMetaByProducer($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    if (is_array($nested)) {
                        $hasNested = true;
                        break 2;
                    }
                }
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }

                $clean = [];
                foreach ($map as $itemId => $indices) {
                    $itemKey = (int) $itemId;
                    if ($itemKey <= 0 || !is_array($indices)) {
                        continue;
                    }
                    $filtered = self::filterIndices($indices);
                    if ($filtered !== []) {
                        $clean[$itemKey] = $filtered;
                    }
                }

                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $clean = [];
        foreach ($raw as $itemId => $indices) {
            $itemKey = (int) $itemId;
            if ($itemKey <= 0 || !is_array($indices)) {
                continue;
            }
            $filtered = self::filterIndices($indices);
            if ($filtered !== []) {
                $clean[$itemKey] = $filtered;
            }
        }

        if ($clean === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $clean];
    }

    /**
     * @param array<int|string,mixed> $indices
     * @return array<int,int>
     */
    private static function filterIndices(array $indices): array
    {
        $clean = [];
        foreach ($indices as $value) {
            $int = (int) $value;
            if ($int < 0) {
                continue;
            }
            $clean[] = $int;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }

    private static function isProducerLineItem($item, int $producerId): bool
    {
        if (!$item instanceof WC_Order_Item_Product) {
            return false;
        }

        $productId = $item->get_product_id();
        if ($productId) {
            $author = (int) get_post_field('post_author', $productId);
            if ($author === $producerId) {
                return true;
            }
        }

        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                $author = (int) get_post_field('post_author', $parentId);
                if ($author === $producerId) {
                    return true;
                }
            }
        }

        return false;
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
