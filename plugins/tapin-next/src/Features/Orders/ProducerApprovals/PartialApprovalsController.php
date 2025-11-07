<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartialApprovalStatus;
use Tapin\Events\Features\Orders\Payments\Orchestrator;
use Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html;
use Tapin\Events\Support\Orders as OrderHelpers;
use WC_Order;
use WC_Order_Item_Product;

final class PartialApprovalsController
{
    /**
     * @param array<int,int> $relevantIds
     * @return array{notice:string}
     */
    public function handle(array $relevantIds, int $producerId): array
    {
        if (!$this->shouldHandleRequest()) {
            return ['notice' => ''];
        }

        $rawPayload = isset($_POST['approve_attendee'])
            ? wp_unslash((array) $_POST['approve_attendee'])
            : [];
        $payload = $this->sanitizePayload($rawPayload);

        if ($payload === []) {
            return ['notice' => ''];
        }

        $now = time();
        $finalization = new FinalizationService();
        $orchestrator = new Orchestrator();

        $ordersSaved = 0;
        $attendeesSaved = 0;
        $blockedItems = 0;
        $errors = 0;

        foreach ($payload as $orderId => $items) {
            if (!in_array($orderId, $relevantIds, true)) {
                $errors++;
                continue;
            }

            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                $errors++;
                continue;
            }

            $orderChanged = false;

            foreach ($items as $itemId => $indices) {
                $item = $order->get_item($itemId);
                if (!$item instanceof WC_Order_Item_Product) {
                    $errors++;
                    continue;
                }

                if (!OrderHelpers::isProducerLineItem($item, $producerId)) {
                    $errors++;
                    continue;
                }

                $eventTimestamp = OrderHelpers::itemEventTimestamp($item);
                if ($eventTimestamp > 0 && $eventTimestamp < $now) {
                    $blockedItems++;
                    continue;
                }

                $quantity = max(0, (int) $item->get_quantity());
                $approvedIndices = $this->sanitizePostedIndices($indices, $quantity);
                $attendeesSaved += count($approvedIndices);
                $orderChanged = true;
                $this->persistItemApprovals($order, $item, $approvedIndices, $quantity);
            }

            if (!$orderChanged) {
                continue;
            }

            $ordersSaved++;
            $approvedMap = $finalization->computeApprovedMap($order);
            [$hasAny, $allApproved] = $this->classifyOrderApprovalState($order, $approvedMap);

            if (!$hasAny) {
                if (!$order->has_status(AwaitingProducerStatus::STATUS_SLUG)) {
                    $order->update_status(AwaitingProducerStatus::STATUS_SLUG, 'Producer approvals reset.');
                }
            } elseif (!$allApproved) {
                if (!$order->has_status(PartialApprovalStatus::STATUS_SLUG)) {
                    $order->update_status(PartialApprovalStatus::STATUS_SLUG, 'Order partially approved by producer.');
                    do_action('tapin_events_order_partially_approved', $orderId, $approvedMap);
                }
            } else {
                do_action('tapin_events_order_finalizing_capture', $orderId, $approvedMap);
                $finalization->applyApprovedToOrder($order, $approvedMap);
                $captureAmount = $finalization->approvedGrandTotal($order, $approvedMap);
                $orchestrator->captureFinalized($order, $approvedMap, $captureAmount);
                do_action('tapin_events_order_captured', $orderId, $approvedMap, $captureAmount);
            }

            $order->save();
        }

        $notice = $this->buildNotice($ordersSaved, $attendeesSaved, $blockedItems, $errors);
        return ['notice' => $notice];
    }

    private function shouldHandleRequest(): bool
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
            return false;
        }

        $nonce = isset($_POST['tapin_pa_bulk_nonce'])
            ? sanitize_text_field((string) wp_unslash($_POST['tapin_pa_bulk_nonce']))
            : '';

        return $nonce !== '' && wp_verify_nonce($nonce, 'tapin_pa_bulk');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<int,array<int,mixed>>>
     */
    private function sanitizePayload(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $orderId => $items) {
            $orderId = (int) $orderId;
            if ($orderId <= 0 || !is_array($items)) {
                continue;
            }

            foreach ($items as $itemId => $indices) {
                $itemId = (int) $itemId;
                if ($itemId <= 0) {
                    continue;
                }

                $normalized[$orderId][$itemId] = (array) $indices;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int|string,mixed> $indices
     * @return array<int,int>
     */
    private function sanitizePostedIndices(array $indices, int $quantity): array
    {
        $approved = [];

        foreach ($indices as $idx => $value) {
            if (!is_numeric($idx)) {
                continue;
            }
            $flag = is_string($value) ? trim($value) : $value;
            $isChecked = ($flag === 'on') || ((int) $flag === 1);
            if (!$isChecked) {
                continue;
            }

            $index = (int) $idx;
            if ($index < 0) {
                continue;
            }
            if ($quantity > 0 && $index >= $quantity) {
                continue;
            }
            $approved[] = $index;
        }

        $approved = array_values(array_unique($approved));
        sort($approved);

        return $approved;
    }

    private function persistItemApprovals(WC_Order $order, WC_Order_Item_Product $item, array $approved, int $quantity): void
    {
        $orderId = (int) $order->get_id();
        $itemId  = (int) $item->get_id();
        $prevApproved = $this->sanitizeStoredIndices((array) $item->get_meta('_tapin_attendees_approved', true), $quantity);

        $maxIndex = $quantity > 0 ? $quantity - 1 : -1;
        for ($idx = 0; $idx <= $maxIndex; $idx++) {
            $was = in_array($idx, $prevApproved, true);
            $is  = in_array($idx, $approved, true);
            if ($was === $is) {
                continue;
            }

            /**
             * Fires when an attendee approval state is changed manually by a producer.
             */
            do_action('tapin_events_attendee_approval_changed', $orderId, $itemId, $idx, $is, 'producer_partial');
        }

        $declined = $quantity > 0 ? array_values(array_diff(range(0, $quantity - 1), $approved)) : [];
        $item->update_meta_data('_tapin_attendees_approved', $approved);
        $item->update_meta_data('_tapin_attendees_declined', $declined);
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,int>
     */
    private function sanitizeStoredIndices(array $values, int $quantity): array
    {
        $clean = [];
        foreach ($values as $value) {
            $index = (int) $value;
            if ($index < 0) {
                continue;
            }
            if ($quantity > 0 && $index >= $quantity) {
                continue;
            }
            $clean[] = $index;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);
        return $clean;
    }

    /**
     * @param array<int,array<string,mixed>> $approvedMap
     * @return array{0:bool,1:bool}
     */
    private function classifyOrderApprovalState(WC_Order $order, array $approvedMap): array
    {
        $hasAny = false;
        $allApproved = true;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId = (int) $item->get_id();
            $quantity = max(0, (int) $item->get_quantity());
            $approvedCount = isset($approvedMap[$itemId]) ? (int) ($approvedMap[$itemId]['approved_count'] ?? 0) : 0;

            if ($approvedCount > 0) {
                $hasAny = true;
            }

            if ($quantity === 0) {
                continue;
            }

            if ($approvedCount < $quantity) {
                $allApproved = false;
            }
        }

        return [$hasAny, $allApproved];
    }

    private function buildNotice(int $ordersSaved, int $attendeesSaved, int $blockedItems, int $errors): string
    {
        $parts = [];

        if ($ordersSaved > 0) {
            $parts[] = sprintf(
                Html::decodeEntities('&#1506;&#1493;&#1491;&#1511;&#1504;&#1493;&#32;%1$d&#32;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;(%2$d&#32;&#1502;&#1513;&#1514;&#1513;&#1514;&#1507;&#1497;&#1501;)'),
                $ordersSaved,
                $attendeesSaved
            );
        }

        if ($blockedItems > 0) {
            $parts[] = sprintf(
                Html::decodeEntities('&#1504;&#1495;&#1505;&#1502;&#1493;&#32;%d&#32;&#1508;&#1512;&#1497;&#1496;&#1497;&#1501;&#32;&#1506;&#1511;&#1489;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1513;&#1502;&#1506;&#1489;&#1512;'),
                $blockedItems
            );
        }

        if ($errors > 0) {
            $parts[] = sprintf(
                Html::decodeEntities('&#1513;&#1490;&#1497;&#1488;&#1493;&#1514;&#58;&#32;%d'),
                $errors
            );
        }

        if ($parts === []) {
            return '';
        }

        $message = implode(' | ', $parts);
        return sprintf(
            '<div class="woocommerce-message" style="direction:rtl;text-align:right">%s</div>',
            esc_html($message)
        );
    }
}
