<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Features\Orders\ProducerApprovalStore;
use Tapin\Events\Support\CapacityValidator;
use Tapin\Events\Support\OrderMeta;
use Tapin\Events\Support\Orders;
use Tapin\Events\Support\PaymentGatewayHelper;
use Tapin\Events\Support\TicketSalesCounter;
use Tapin\Events\Support\EventStockSynchronizer;
use Tapin\Events\Support\AttendeeSecureStorage;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class BulkActionsController
{
    private const LEGACY_PRODUCER_ID = 0;

    /**
     * @var array<int,string>
     */
    private array $warnings = [];

    /**
     * @param array<int,int> $relevantIds
     * @return array{notice: string}
     */
    public function handle(array $relevantIds): array
    {
        $notice = '';

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            $nonce = $_POST['tapin_pa_bulk_nonce'] ?? '';
            if (empty($nonce) || !wp_verify_nonce((string) $nonce, 'tapin_pa_bulk')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(
                        '[Tapin ProducerApproval] Invalid or missing nonce on bulk handler for user '
                        . (int) get_current_user_id()
                        . ' and order IDs: '
                        . (json_encode($_POST['order_ids'] ?? []) ?: '[]')
                    );
                }

                return [
                    'notice' => sprintf(
                        '<div class="woocommerce-error" style="direction:rtl;text-align:right">%s</div>',
                        esc_html__('הבקשה לא אושרה – אנא רענן את העמוד ונסה שוב.', 'tapin')
                    ),
                ];
            }

            $approveAll     = !empty($_POST['approve_all']);
            $cancelSelected = isset($_POST['bulk_cancel']);
            $bulkApprove    = isset($_POST['bulk_approve']);

            $selected    = array_map('absint', (array) ($_POST['order_ids'] ?? []));
            $attendeeMap = $this->sanitizeAttendeeSelection((array) ($_POST['attendee_approve'] ?? []));
            $hasSelection = $selected !== [] || $attendeeMap !== [];

            if (!$bulkApprove && !$approveAll && !$cancelSelected && $hasSelection) {
                $bulkApprove = true;
            }

            if ($approveAll) {
                $selected = $relevantIds;
            } elseif ($cancelSelected && $selected === [] && $attendeeMap !== []) {
                $selected = array_map('intval', array_keys($attendeeMap));
            }

            $orderLevelApprove = $approveAll || $cancelSelected;
            if (!$orderLevelApprove && $bulkApprove && $attendeeMap === [] && $selected !== []) {
                $orderLevelApprove = true;
            }

            if ($bulkApprove && !$orderLevelApprove && $attendeeMap === []) {
                return [
                    'notice' => sprintf(
                        '<div class="woocommerce-error" style="direction:rtl;text-align:right">%s</div>',
                        __('לא נבחרו משתתפים לאישור.', 'tapin')
                    ),
                ];
            }

            $branch = $orderLevelApprove ? ($cancelSelected ? 'order_cancel' : 'order_full') : ($bulkApprove ? 'attendee' : 'none');
            $this->logDebug(sprintf(
                'PA handle: producer=%d branch=%s approveAll=%d cancel=%d bulk=%d selected=%s attendeeOrders=%s',
                (int) get_current_user_id(),
                $branch,
                $approveAll ? 1 : 0,
                $cancelSelected ? 1 : 0,
                $bulkApprove ? 1 : 0,
                json_encode(array_values($selected), JSON_UNESCAPED_SLASHES) ?: '[]',
                json_encode(array_keys($attendeeMap), JSON_UNESCAPED_SLASHES) ?: '[]'
            ));

            $approved = 0;
            $failed   = 0;

            if ($orderLevelApprove) {
                [$approved, $failed] = $this->handleOrderLevelActions(
                    array_unique(array_map('intval', $selected)),
                    $relevantIds,
                    $cancelSelected
                );
            } elseif ($bulkApprove) {
                [$approved, $failed] = $this->handleAttendeeApprovals($attendeeMap, $relevantIds);
            }

            if ($approved || $failed) {
                $notice = sprintf(
                    '<div class="woocommerce-message" style="direction:rtl;text-align:right">%s</div>',
                    sprintf(
                        esc_html__('אושרו %1$d הזמנות, נכשלו %2$d.', 'tapin'),
                        $approved,
                        $failed
                    )
                );
            }

            if ($this->warnings !== []) {
                $warningText = implode(' ', array_unique(array_map('wp_strip_all_tags', $this->warnings)));
                if ($warningText !== '') {
                    $notice .= sprintf(
                        '<div class="woocommerce-error" style="direction:rtl;text-align:right">%s</div>',
                        esc_html($warningText)
                    );
                }
                $this->warnings = [];
            }

            if ($notice === '' && ($approveAll || $cancelSelected || $bulkApprove || $hasSelection)) {
                $notice = sprintf(
                    '<div class="woocommerce-error" style="direction:rtl;text-align:right">%s</div>',
                    esc_html__('No approvals were processed. Please select attendees or orders and try again.', 'tapin')
                );
            }
        }

        return ['notice' => $notice];
    }

    /**
     * @param array<int,int> $selected
     * @param array<int,int> $relevantIds
     * @return array{int,int}
     */
    private function handleOrderLevelActions(array $selected, array $relevantIds, bool $cancelSelected): array
    {
        $approved       = 0;
        $failed         = 0;
        $relevantLookup = array_fill_keys(array_map('intval', $relevantIds), true);
        $producerId     = (int) get_current_user_id();

        foreach ($selected as $orderId) {
            $orderId = (int) $orderId;
            $this->logDebug(sprintf(
                'PA order-level: start order=%d producer=%d cancel=%d',
                $orderId,
                $producerId,
                $cancelSelected ? 1 : 0
            ));
            if (!isset($relevantLookup[$orderId])) {
                $this->logDebug(sprintf('PA order-level: order %d skipped (not relevant for producer)', $orderId));
                $failed++;
                continue;
            }

            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                $this->logDebug(sprintf('PA order-level: order %d not found', $orderId));
                $failed++;
                continue;
            }

            $status = $order->get_status();
            if (!in_array($status, [AwaitingProducerStatus::STATUS_SLUG, PartiallyApprovedStatus::STATUS_SLUG], true)) {
                $this->logDebug(sprintf('PA order-level: order %d skipped due to status %s', $orderId, $status));
                $order->add_order_note(sprintf(
                    'Tapin: producer %d action skipped because status is %s.',
                    $producerId,
                    $status
                ));
                $failed++;
                continue;
            }

            if ($cancelSelected) {
                $this->logDebug(sprintf('PA order-level: cancelling order %d for producer %d', $orderId, $producerId));
                $order->add_order_note(sprintf('Tapin: producer %d requested cancellation from approvals screen.', $producerId));
                $producerProducts = $this->productIdsFromItems($this->collectProducerItems($order, $producerId));
                $this->syncTicketSales($order, [], $producerProducts);
                $this->persistApprovalMeta($order, [], [], 0.0, $producerId);

                $capturedAmount = $this->resolveProducerCapturedTotal($order, $producerId);
                $amount         = $capturedAmount > 0.0
                    ? $capturedAmount
                    : $this->resolveProducerPartialTotal($order, $producerId);
                if ($amount <= 0.0) {
                    $amount = (float) $order->get_total();
                }

                if ($amount > 0.0) {
                    $refunded = PaymentGatewayHelper::maybeRefund($order, $amount, 'Tapin approval cancelled');
                    if (!$refunded) {
                        $order->add_order_note(__('Payment not voided automatically during cancellation.', 'tapin'));
                    }
                }

                $this->clearAllPartialApprovalState($order);
                $order->update_status(
                    'cancelled',
                    __('Cancellation requested while awaiting producer approval.', 'tapin')
                );
                $order->save();
                $approved++;
                continue;
            }

            if ($producerId <= 0) {
                $this->logDebug(sprintf('PA order-level: producer id missing for order %d', $orderId));
                $order->add_order_note('Tapin: producer approval failed because the producer id was missing.');
                $failed++;
                continue;
            }

            $producerItems = $this->collectProducerItems($order, $producerId);
            if ($producerItems === []) {
                $this->logDebug(sprintf('PA order-level: no producer items for order %d (producer %d)', $orderId, $producerId));
                $order->add_order_note(sprintf('Tapin: no matching items for producer %d on this order.', $producerId));
                $failed++;
                continue;
            }

            $fullMeta = [];
            foreach ($producerItems as $itemId => $item) {
                $quantity = max(0, (int) $item->get_quantity());
                if ($quantity <= 0) {
                    continue;
                }
                $fullMeta[(int) $itemId] = range(0, $quantity - 1);
            }

            if ($fullMeta === []) {
                $this->logDebug(sprintf('PA order-level: no quantities to approve for order %d (producer %d)', $orderId, $producerId));
                $order->add_order_note(sprintf('Tapin: producer %d approval skipped because quantities were empty.', $producerId));
                $failed++;
                continue;
            }

            $attendees     = $this->buildApprovedAttendeeList($order, $producerItems, $fullMeta);
            $desiredCounts = $this->countsFromAttendees($attendees);
            $recorded      = $this->getRecordedSalesMap($order);
            $hasCapacity   = true;

            foreach ($desiredCounts as $productId => $typeCounts) {
                $summary = CapacityValidator::summarize((int) $productId);
                $check   = CapacityValidator::canAllocate($typeCounts, $recorded[$productId] ?? [], $summary);
                if (!$check['ok']) {
                    $this->warnings[] = sprintf(
                        __('Order #%s could not be fully approved because availability was exceeded.', 'tapin'),
                        $order->get_order_number()
                    );
                    $hasCapacity = false;
                    break;
                }
            }

            if (!$hasCapacity) {
                $this->logDebug(sprintf('PA order-level: capacity check failed for order %d (producer %d)', $orderId, $producerId));
                $order->add_order_note(sprintf('Tapin: approval blocked for producer %d because capacity was exceeded.', $producerId));
                $failed++;
                continue;
            }

            $stateSnapshot = $this->snapshotProducerState($order);

            if (!$this->syncTicketSales($order, $desiredCounts, $this->productIdsFromItems($producerItems))) {
                $this->logDebug(sprintf('PA order-level: ticket sales sync failed for order %d (producer %d)', $orderId, $producerId));
                $order->add_order_note(sprintf('Tapin: ticket allocation failed for producer %d; approval rolled back.', $producerId));
                $failed++;
                continue;
            }

            $partialData = $this->computePartialData($order, $producerItems, $fullMeta);
            $this->persistApprovalMeta($order, $fullMeta, $partialData['map'], $partialData['total'], $producerId);

            $existingMap    = $this->normalizeProducerPartialMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_MAP, true), $producerId);
            unset($existingMap[$producerId]);
            $this->saveProducerPartialMap($order, $existingMap);

            $existingTotals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_TOTAL, true), $producerId);
            unset($existingTotals[$producerId]);
            $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_APPROVED_TOTAL, $existingTotals);
            $order->save();

            $captured = AwaitingProducerGate::captureAndApprove($order, $producerId, $partialData['total']);
            if (!$captured) {
                $this->logDebug(sprintf('PA order-level: capture failed for order %d (producer %d)', $orderId, $producerId));
                $this->restoreProducerState($order, $stateSnapshot);
                $order->add_order_note(__('Tapin: final capture failed. Approval was rolled back and capacity released for this producer.', 'tapin'));
                $order->save();
                $failed++;
                continue;
            }

            $order->add_order_note(sprintf('Tapin: order-level approval completed for producer %d.', $producerId));
            $this->logDebug(sprintf('PA order-level: approval complete for order %d (producer %d)', $orderId, $producerId));
            $approved++;
        }

        return [$approved, $failed];
    }

    /**
     * @param array<int,array<int,array<int,int>>> $selection
     * @param array<int,int> $relevantIds
     * @return array{int,int}
     */
    private function handleAttendeeApprovals(array $selection, array $relevantIds): array
    {
        if ($selection === []) {
            $this->logDebug('PA attendee: selection empty');
            return [0, 0];
        }

        $producerId = (int) get_current_user_id();
        if ($producerId <= 0) {
            $this->logDebug('PA attendee: missing producer id');
            return [0, count($selection)];
        }

        $approved       = 0;
        $failed         = 0;
        $relevantLookup = array_fill_keys(array_map('intval', $relevantIds), true);

        foreach ($selection as $orderId => $items) {
            $orderKey = (int) $orderId;
            $this->logDebug(sprintf('PA attendee: start order=%d producer=%d', $orderKey, $producerId));
            if (!isset($relevantLookup[$orderKey])) {
                $this->logDebug(sprintf('PA attendee: order %d not relevant for producer', $orderKey));
                $failed++;
                continue;
            }

            $order = wc_get_order($orderKey);
            if (!$order instanceof WC_Order) {
                $this->logDebug(sprintf('PA attendee: order %d not found', $orderKey));
                $failed++;
                continue;
            }

            $status = $order->get_status();
            if (!in_array($status, [AwaitingProducerStatus::STATUS_SLUG, PartiallyApprovedStatus::STATUS_SLUG], true)) {
                $this->logDebug(sprintf('PA attendee: order %d skipped due to status %s', $orderKey, $status));
                $order->add_order_note(sprintf(
                    'Tapin: producer %d attendee approval skipped because status is %s.',
                    $producerId,
                    $status
                ));
                $failed++;
                continue;
            }

            if ($this->applyAttendeeSelection($order, $producerId, is_array($items) ? $items : [])) {
                $this->logDebug(sprintf('PA attendee: approval applied for order %d (producer %d)', $orderKey, $producerId));
                $approved++;
            } else {
                $this->logDebug(sprintf('PA attendee: approval failed for order %d (producer %d)', $orderKey, $producerId));
                $failed++;
            }
        }

        return [$approved, $failed];
    }


    /**
     * @param array<int,array<int,int>> $itemSelections
     */
    private function applyAttendeeSelection(WC_Order $order, int $producerId, array $itemSelections): bool
    {
        $orderId = (int) $order->get_id();
        $this->logDebug(sprintf(
            'PA attendee-selection: order=%d producer=%d itemKeys=%s',
            $orderId,
            $producerId,
            json_encode(array_keys($itemSelections), JSON_UNESCAPED_SLASHES) ?: '[]'
        ));
        $producerItems = $this->collectProducerItems($order, $producerId);
        if ($producerItems === []) {
            $this->logDebug(sprintf('PA attendee-selection: no producer items for order %d (producer %d)', $orderId, $producerId));
            $order->add_order_note(sprintf('Tapin: no matching items for producer %d when applying attendee selection.', $producerId));
            return false;
        }

        $approvedMetaByProducer = $this->normalizeApprovedMetaByProducer(
            (array) $order->get_meta(OrderMeta::PRODUCER_APPROVED_ATTENDEES, true),
            $producerId
        );
        $approvedMeta = $approvedMetaByProducer[$producerId]
            ?? $approvedMetaByProducer[self::LEGACY_PRODUCER_ID]
            ?? [];

        $selectionMeta = $this->buildSelectionMeta($producerItems, $itemSelections, $approvedMeta);
        if ($selectionMeta['touched'] === false) {
            $this->logDebug(sprintf('PA attendee-selection: no attendees selected for order %d (producer %d)', $orderId, $producerId));
            $order->add_order_note(sprintf('Tapin: producer %d submitted approval with no attendees selected.', $producerId));
            return false;
        }

        $capacityResult    = $this->enforceCapacityForSelection($order, $producerItems, $selectionMeta['meta']);
        $finalApprovedMeta = $capacityResult['approved_meta'];
        $desiredCounts     = $capacityResult['counts'];

        $partialData = $this->computePartialData($order, $producerItems, $finalApprovedMeta);
        $this->logDebug(sprintf(
            'PA attendee-selection: summary order=%d producer=%d touched=%d approved_qty=%d total_qty=%d total=%s',
            $orderId,
            $producerId,
            $selectionMeta['touched'] ? 1 : 0,
            (int) ($partialData['approved_qty'] ?? 0),
            (int) ($partialData['total_qty'] ?? 0),
            (string) ($partialData['total'] ?? 0)
        ));

        $stateSnapshot = $this->snapshotProducerState($order);
        $previousStatus = $order->get_status();

        if (!$this->syncTicketSales($order, $desiredCounts, $this->productIdsFromItems($producerItems))) {
            $this->logDebug(sprintf('PA attendee-selection: ticket sync failed for order %d (producer %d)', $orderId, $producerId));
            $order->add_order_note(sprintf('Tapin: ticket allocation failed for producer %d; approval was not saved.', $producerId));
            $order->save();
            return false;
        }

        $this->persistApprovalMeta($order, $finalApprovedMeta, $partialData['map'], $partialData['total'], $producerId);

        if ($partialData['approved_qty'] <= 0) {
            $order->update_status(
                AwaitingProducerStatus::STATUS_SLUG,
                __('No attendees approved yet', 'tapin')
            );
            $order->save();
            return true;
        }

        if ($partialData['approved_qty'] < $partialData['total_qty']) {
            $order->update_status(
                PartiallyApprovedStatus::STATUS_SLUG,
                __('Partial approval saved', 'tapin')
            );
            $order->save();

            $capturedPartial = $this->capturePartialIfSupported($order, $producerId, $partialData['total']);

            if ($capturedPartial) {
                do_action('tapin/events/order/producer_partial_approval', $order, $producerId);
                do_action('tapin/events/order/producer_attendees_approved', $order, $producerId);
                $order->save();
                return true;
            }

            $this->logDebug(sprintf('PA attendee-selection: partial capture failed for order %d (producer %d)', $orderId, $producerId));
            $this->restoreProducerState($order, $stateSnapshot);
            $order->set_status($previousStatus);
            $order->add_order_note(__('Tapin: partial capture failed. Approval was rolled back and capacity released.', 'tapin'));
            $order->save();

            return false;
        }

        $existingMap    = $this->normalizeProducerPartialMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_MAP, true), $producerId);
        unset($existingMap[$producerId]);
        $this->saveProducerPartialMap($order, $existingMap);

        $existingTotals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_TOTAL, true), $producerId);
        unset($existingTotals[$producerId]);
        $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_APPROVED_TOTAL, $existingTotals);
        $order->save();

        $captured = AwaitingProducerGate::captureAndApprove($order, $producerId, $partialData['total']);
        if (!$captured) {
            $this->logDebug(sprintf('PA attendee-selection: final capture failed for order %d (producer %d)', $orderId, $producerId));
            $this->restoreProducerState($order, $stateSnapshot);
            $order->add_order_note(__('Tapin: final capture failed. Approval was rolled back and capacity released for this producer.', 'tapin'));
            $order->save();
            return false;
        }

        $this->logDebug(sprintf('PA attendee-selection: approval complete for order %d (producer %d)', $orderId, $producerId));
        return true;
    }

    /**
     * @return array<int,WC_Order_Item_Product>
     */
    private function collectProducerItems(WC_Order $order, int $producerId): array
    {
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product && $this->isProducerLineItem($item, $producerId)) {
                $items[(int) $item->get_id()] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<int,WC_Order_Item_Product> $producerItems
     * @param array<int,array<int,int>> $itemSelections
     * @param array<int,array<int,int>> $existingMeta
     * @return array{meta: array<int,array<int,int>>, touched: bool}
     */
    private function buildSelectionMeta(array $producerItems, array $itemSelections, array $existingMeta): array
    {
        $meta    = $existingMeta;
        $touched = false;

        foreach ($producerItems as $itemId => $item) {
            if (!array_key_exists($itemId, $itemSelections)) {
                unset($meta[$itemId]);
                continue;
            }

            $touched  = true;
            $quantity = max(0, (int) $item->get_quantity());
            $filtered = $this->filterIndices($itemSelections[$itemId], $quantity > 0 ? $quantity : PHP_INT_MAX);

            if ($filtered === []) {
                unset($meta[$itemId]);
                continue;
            }

            $meta[$itemId] = $filtered;
        }

        return ['meta' => $meta, 'touched' => $touched];
    }

    /**
     * @param array<int,WC_Order_Item_Product> $producerItems
     * @param array<int,array<int,int>> $approvedMeta
     * @return array{approved_meta: array<int,array<int,int>>, counts: array<int,array<string,int>>}
     */
    private function enforceCapacityForSelection(WC_Order $order, array $producerItems, array $approvedMeta): array
    {
        if ($approvedMeta === []) {
            return [
                'approved_meta' => [],
                'counts'        => [],
            ];
        }

        $recorded  = $this->getRecordedSalesMap($order);
        $attendees = $this->buildApprovedAttendeeList($order, $producerItems, $approvedMeta);

        if ($attendees === []) {
            return [
                'approved_meta' => [],
                'counts'        => [],
            ];
        }

        $requestedCounts = $this->countsFromAttendees($attendees);
        $dropMap         = [];
        $capacityCache   = [];

        foreach ($requestedCounts as $productId => $typeCounts) {
            $summary = $capacityCache[$productId] ?? CapacityValidator::summarize((int) $productId);
            $capacityCache[$productId] = $summary;
            $recordedTypes             = $recorded[$productId] ?? [];

            foreach ($typeCounts as $typeId => $qty) {
                $typeMeta  = $summary['types'][$typeId] ?? ['remaining' => -1];
                $available = ($typeMeta['remaining'] < 0 ? PHP_INT_MAX : (int) $typeMeta['remaining']) + max(0, (int) ($recordedTypes[$typeId] ?? 0));

                if ($qty > $available) {
                    $dropMap[$productId][$typeId] = $qty - $available;
                    $this->warnings[] = sprintf(
                        __('Capacity reached for ticket type %s on order #%s', 'tapin'),
                        $typeId !== '' ? $typeId : __('ticket', 'tapin'),
                        $order->get_order_number()
                    );
                }
            }
        }

        if ($dropMap !== []) {
            $attendees = $this->dropAttendees($attendees, $dropMap);
        }

        $finalMeta   = $this->rebuildMetaFromAttendees($attendees);
        $finalCounts = $this->countsFromAttendees($attendees);

        return [
            'approved_meta' => $finalMeta,
            'counts'        => $finalCounts,
        ];
    }

    /**
     * @param array<int,WC_Order_Item_Product> $producerItems
     * @param array<int,array<int,int>> $approvedMeta
     * @return array<int,array<string,mixed>>
     */
    private function buildApprovedAttendeeList(WC_Order $order, array $producerItems, array $approvedMeta): array
    {
        $attendees = [];

        foreach ($producerItems as $itemId => $item) {
            $selected = $approvedMeta[$itemId] ?? [];
            if ($selected === []) {
                continue;
            }

            $rows     = $this->extractAttendeesForItem($item);
            $quantity = max(0, (int) $item->get_quantity());

            foreach ($selected as $index) {
                $intIndex = (int) $index;
                if ($intIndex < 0 || ($quantity > 0 && $intIndex >= $quantity)) {
                    continue;
                }

                $row       = $rows[$intIndex] ?? [];
                $typeId    = isset($row['ticket_type']) ? sanitize_key((string) $row['ticket_type']) : '';
                $label     = isset($row['ticket_type_label']) ? sanitize_text_field((string) $row['ticket_type_label']) : '';
                $productId = $this->resolveProductId($item);

                $attendees[] = [
                    'item_id'        => (int) $itemId,
                    'attendee_index' => $intIndex,
                    'product_id'     => $productId,
                    'ticket_type'    => $typeId !== '' ? $typeId : 'general',
                    'ticket_label'   => $label,
                ];
            }
        }

        return $attendees;
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     * @param array<int,array<string,int>> $dropMap
     * @return array<int,array<string,mixed>>
     */
    private function dropAttendees(array $attendees, array $dropMap): array
    {
        $kept = [];

        foreach ($attendees as $attendee) {
            $productId = (int) ($attendee['product_id'] ?? 0);
            $typeId    = isset($attendee['ticket_type']) ? (string) $attendee['ticket_type'] : 'general';
            if ($typeId === '') {
                $typeId = 'general';
            }

            if ($productId > 0 && isset($dropMap[$productId][$typeId]) && $dropMap[$productId][$typeId] > 0) {
                $dropMap[$productId][$typeId]--;
                continue;
            }

            $kept[] = $attendee;
        }

        return $kept;
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     * @return array<int,array<string,int>>
     */
    private function countsFromAttendees(array $attendees): array
    {
        $counts = [];

        foreach ($attendees as $attendee) {
            $productId = (int) ($attendee['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $typeId = isset($attendee['ticket_type']) ? (string) $attendee['ticket_type'] : '';
            if ($typeId === '') {
                $typeId = 'general';
            }

            if (!isset($counts[$productId])) {
                $counts[$productId] = [];
            }

            $counts[$productId][$typeId] = ($counts[$productId][$typeId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int,array<string,mixed>> $attendees
     * @return array<int,array<int,int>>
     */
    private function rebuildMetaFromAttendees(array $attendees): array
    {
        $meta = [];

        foreach ($attendees as $attendee) {
            $itemId = (int) ($attendee['item_id'] ?? 0);
            $index  = (int) ($attendee['attendee_index'] ?? -1);

            if ($itemId <= 0 || $index < 0) {
                continue;
            }

            if (!isset($meta[$itemId])) {
                $meta[$itemId] = [];
            }

            $meta[$itemId][] = $index;
        }

        foreach ($meta as $itemId => $indices) {
            $meta[$itemId] = $this->filterIndices($indices, PHP_INT_MAX);
        }

        return $meta;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractAttendeesForItem(WC_Order_Item_Product $item): array
    {
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));

        if ($decoded === []) {
            $legacy = (string) $item->get_meta('Tapin Attendees', true);
            if ($legacy !== '') {
                $decoded = AttendeeSecureStorage::decrypt($legacy);
            }
        }

        if ($decoded === []) {
            $order = $item->get_order();
            if ($order instanceof WC_Order) {
                $aggregate = $order->get_meta('_tapin_attendees', true);
                $decoded   = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            }
        }

        $normalized = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalized[] = [
                'ticket_type'       => sanitize_key((string) ($entry['ticket_type'] ?? '')),
                'ticket_type_label' => sanitize_text_field((string) ($entry['ticket_type_label'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function resolveProductId(WC_Order_Item_Product $item): int
    {
        $product = $item->get_product();
        if ($product instanceof WC_Product) {
            $parentId = $product->get_parent_id();
            if ($parentId) {
                return (int) $parentId;
            }

            return (int) $product->get_id();
        }

        return (int) $item->get_product_id();
    }

    /**
     * @param array<int,WC_Order_Item_Product> $producerItems
     * @param array<int,array<int,int>> $approvedMeta
     * @return array{map: array<int,int>, total: float, total_qty: int, approved_qty: int}
     */
    private function computePartialData(WC_Order $order, array $producerItems, array $approvedMeta): array
    {
        $partialMap       = [];
        $partialTotal     = 0.0;
        $producerTotal    = 0;
        $producerApproved = 0;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemId   = (int) $item->get_id();
            $quantity = max(0, (int) $item->get_quantity());

            if (isset($approvedMeta[$itemId])) {
                $clean = $this->filterIndices($approvedMeta[$itemId], $quantity > 0 ? $quantity : PHP_INT_MAX);
                if ($clean !== []) {
                    $count = min($quantity, count($clean));
                    if ($count > 0) {
                        $partialMap[$itemId] = $count;
                        $unit        = $quantity > 0
                            ? ((float) $item->get_total() / max(1, $quantity))
                            : (float) $item->get_total();
                        $partialTotal += $unit * $count;
                    }
                }
            }

            if (isset($producerItems[$itemId])) {
                $producerTotal += $quantity;
                if (isset($partialMap[$itemId])) {
                    $producerApproved += min($quantity, (int) $partialMap[$itemId]);
                }
            }
        }

        return [
            'map'          => $partialMap,
            'total'        => $partialTotal,
            'total_qty'    => $producerTotal,
            'approved_qty' => $producerApproved,
        ];
    }

    private function capturePartialIfSupported(WC_Order $order, int $producerId, float $approvedTotal): bool
    {
        $target = max(0.0, $approvedTotal);
        $alreadyCaptured = $this->resolveProducerCapturedTotal($order, $producerId);
        $toCapture       = $target - $alreadyCaptured;

        if ($toCapture <= 0.0) {
            return true;
        }

        $gateway = PaymentGatewayHelper::getGateway($order);
        if (!PaymentGatewayHelper::supportsPartialCapture($gateway)) {
            $order->add_order_note(__('Gateway does not support partial capture; approval saved without capture. Please charge manually.', 'tapin'));
            $capturedTotals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_CAPTURED_TOTAL, true), $producerId);
            $capturedTotals[$producerId] = max($capturedTotals[$producerId] ?? 0.0, $target);
            $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_CAPTURED_TOTAL, $capturedTotals);
            return true;
        }

        $captured = PaymentGatewayHelper::capture($order, $toCapture);
        if ($captured) {
            $allCaptured = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_CAPTURED_TOTAL, true), $producerId);
            $allCaptured[$producerId] = ($allCaptured[$producerId] ?? 0.0) + $toCapture;
            $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_CAPTURED_TOTAL, $allCaptured);

            return true;
        }

        $order->add_order_note(__('Partial capture failed. Approval saved without capture; please charge manually.', 'tapin'));
        $capturedTotals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_CAPTURED_TOTAL, true), $producerId);
        $capturedTotals[$producerId] = max($capturedTotals[$producerId] ?? 0.0, $alreadyCaptured);
        $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_CAPTURED_TOTAL, $capturedTotals);

        return true;
    }

    /**
     * @param array<int,array<string,int>> $desired
     * @param array<int,int> $limitProductIds
     */
    private function syncTicketSales(WC_Order $order, array $desired, array $limitProductIds = []): bool
    {
        $recorded = $this->getRecordedSalesMap($order);

        $limitLookup = [];

        $sanitizedDesired = [];
        foreach ($desired as $productId => $typeCounts) {
            $pid = (int) $productId;
            if ($pid <= 0 || !is_array($typeCounts)) {
                continue;
            }

            foreach ($typeCounts as $typeId => $count) {
                $tid = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
                if ($tid === '') {
                    continue;
                }

                $sanitizedDesired[$pid][$tid] = max(0, (int) $count);
            }
        }

        if ($limitProductIds !== []) {
            $limitLookup = [];
            foreach ($limitProductIds as $limitId) {
                $pid = (int) $limitId;
                if ($pid > 0) {
                    $limitLookup[$pid] = true;
                }
            }

            if ($limitLookup !== []) {
                foreach (array_keys($sanitizedDesired) as $pid) {
                    if (!isset($limitLookup[$pid])) {
                        unset($sanitizedDesired[$pid]);
                    }
                }
            }
        }

        if ($sanitizedDesired === [] && $recorded === []) {
            return true;
        }

        foreach ($sanitizedDesired as $productId => $typeCounts) {
            $summary       = CapacityValidator::summarize($productId);
            $recordedTypes = $recorded[$productId] ?? [];
            $check         = CapacityValidator::canAllocate($typeCounts, $recordedTypes, $summary);

            if (!$check['ok']) {
                foreach ($check['insufficient'] as $typeId => $_meta) {
                    $this->warnings[] = sprintf(
                        __('Not enough remaining tickets for %s on order #%s', 'tapin'),
                        $typeId !== '' ? $typeId : __('ticket', 'tapin'),
                        $order->get_order_number()
                    );
                }

                return false;
            }
        }

        $limitedProducts = [];
        if (!empty($limitLookup)) {
            $limitedProducts = array_keys($limitLookup);
        }

        $allProducts = $limitedProducts !== []
            ? $limitedProducts
            : array_unique(array_merge(array_keys($recorded), array_keys($sanitizedDesired)));

        foreach ($allProducts as $productId) {
            $pid = (int) $productId;
            if ($pid <= 0) {
                continue;
            }

            $deltaMap      = [];
            $recordedTypes = $recorded[$pid] ?? [];
            $targetTypes   = $sanitizedDesired[$pid] ?? [];
            $allTypes      = array_unique(array_merge(array_keys($recordedTypes), array_keys($targetTypes)));

            foreach ($allTypes as $typeId) {
                $tid     = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
                $target  = $targetTypes[$tid] ?? 0;
                $current = $recordedTypes[$tid] ?? 0;
                $delta   = $target - $current;

                if ($delta !== 0) {
                    $deltaMap[$tid] = $delta;
                }
            }

            if ($deltaMap !== []) {
                TicketSalesCounter::adjust($pid, $deltaMap);
            }
        }

        $updatedMap = $recorded;
        foreach ($allProducts as $productId) {
            $pid = (int) $productId;
            if ($pid <= 0) {
                continue;
            }

            $updatedMap[$pid] = $sanitizedDesired[$pid] ?? [];
            if ($updatedMap[$pid] === []) {
                unset($updatedMap[$pid]);
            }
        }

        foreach ($allProducts as $productId) {
            $pid = (int) $productId;
            if ($pid > 0) {
                EventStockSynchronizer::syncFromTicketTypes($pid);
            }
        }

        $this->storeRecordedSales($order, $updatedMap);

        return true;
    }

    /**
     * @return array<int,array<string,int>>
     */
    private function getRecordedSalesMap(WC_Order $order): array
    {
        $raw = $order->get_meta(OrderMeta::TICKET_SALES_RECORDED, true);
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
                if ($tid === '') {
                    continue;
                }

                $result[$pid][$tid] = max(0, (int) $count);
            }
        }

        return $result;
    }

    /**
     * @param array<int,array<string,int>> $map
     */
    private function storeRecordedSales(WC_Order $order, array $map): void
    {
        $clean = [];

        foreach ($map as $productId => $types) {
            $pid = (int) $productId;
            if ($pid <= 0 || !is_array($types)) {
                continue;
            }

            foreach ($types as $typeId => $count) {
                $tid = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
                $val = max(0, (int) $count);

                if ($tid === '' || $val <= 0) {
                    continue;
                }

                if (!isset($clean[$pid])) {
                    $clean[$pid] = [];
                }

                $clean[$pid][$tid] = $val;
            }
        }

        if ($clean === []) {
            $order->delete_meta_data(OrderMeta::TICKET_SALES_RECORDED);
        } else {
            $order->update_meta_data(OrderMeta::TICKET_SALES_RECORDED, $clean);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshotProducerState(WC_Order $order): array
    {
        return [
            'sales_map'      => $this->getRecordedSalesMap($order),
            'approved_meta'  => $order->get_meta(OrderMeta::PRODUCER_APPROVED_ATTENDEES, true),
            'partial_map'    => $order->get_meta(OrderMeta::PARTIAL_APPROVED_MAP, true),
            'partial_totals' => $order->get_meta(OrderMeta::PARTIAL_APPROVED_TOTAL, true),
        ];
    }

    private function restoreProducerState(WC_Order $order, array $snapshot): void
    {
        $targetSales  = isset($snapshot['sales_map']) && is_array($snapshot['sales_map']) ? $snapshot['sales_map'] : [];
        $currentSales = $this->getRecordedSalesMap($order);
        $deltaMap     = $this->buildSalesDelta($targetSales, $currentSales);

        foreach ($deltaMap as $productId => $types) {
            TicketSalesCounter::adjust($productId, $types);
        }

        $this->storeRecordedSales($order, $targetSales);

        $this->restoreMetaValue($order, OrderMeta::PRODUCER_APPROVED_ATTENDEES, $snapshot['approved_meta'] ?? null);
        $this->restoreMetaValue($order, OrderMeta::PARTIAL_APPROVED_MAP, $snapshot['partial_map'] ?? null);
        $this->restoreMetaValue($order, OrderMeta::PARTIAL_APPROVED_TOTAL, $snapshot['partial_totals'] ?? null);
    }

    private function clearAllPartialApprovalState(WC_Order $order): void
    {
        $order->delete_meta_data(OrderMeta::PARTIAL_CAPTURED_TOTAL);
        $order->delete_meta_data(OrderMeta::PARTIAL_APPROVED_MAP);
        $order->delete_meta_data(OrderMeta::PARTIAL_APPROVED_TOTAL);
        $order->delete_meta_data(OrderMeta::PRODUCER_APPROVED_ATTENDEES);
    }

    /**
     * @param array<int,array<string,int>> $target
     * @param array<int,array<string,int>> $current
     * @return array<int,array<string,int>>
     */
    private function buildSalesDelta(array $target, array $current): array
    {
        $delta       = [];
        $allProducts = array_unique(array_merge(array_keys($target), array_keys($current)));

        foreach ($allProducts as $productId) {
            $pid = (int) $productId;
            if ($pid <= 0) {
                continue;
            }

            $targetTypes  = $target[$pid] ?? [];
            $currentTypes = $current[$pid] ?? [];
            $allTypes     = array_unique(array_merge(array_keys($targetTypes), array_keys($currentTypes)));

            foreach ($allTypes as $typeId) {
                $tid = is_string($typeId) || is_numeric($typeId) ? (string) $typeId : '';
                if ($tid === '') {
                    continue;
                }

                $targetVal  = max(0, (int) ($targetTypes[$tid] ?? 0));
                $currentVal = max(0, (int) ($currentTypes[$tid] ?? 0));
                $diff       = $targetVal - $currentVal;

                if ($diff !== 0) {
                    $delta[$pid][$tid] = $diff;
                }
            }
        }

        return $delta;
    }

    private function restoreMetaValue(WC_Order $order, string $key, $value): void
    {
        if ($value === null || $value === [] || $value === '') {
            $order->delete_meta_data($key);
            return;
        }

        $order->update_meta_data($key, $value);
    }

    private function logDebug(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tapin PA] ' . $message);
        }
    }

    /**
     * @param array<int,WC_Order_Item_Product> $items
     * @return array<int,int>
     */
    private function productIdsFromItems(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $pid = $this->resolveProductId($item);
            if ($pid > 0) {
                $ids[$pid] = $pid;
            }
        }

        return array_values($ids);
    }



    /**
     * @param array<int,array<int,int>> $approvedMeta
     * @param array<int,int> $partialMap
     */
    private function persistApprovalMeta(WC_Order $order, array $approvedMeta, array $partialMap, float $partialTotal, int $producerId): void
    {
        $targetProducer = $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;
        $approvedByProducer = $this->normalizeApprovedMetaByProducer(
            (array) $order->get_meta(OrderMeta::PRODUCER_APPROVED_ATTENDEES, true),
            $targetProducer
        );
        $cleanApproved = $this->normalizeApprovedMeta($approvedMeta);

        if ($cleanApproved === []) {
            unset($approvedByProducer[$targetProducer]);
        } else {
            $approvedByProducer[$targetProducer] = $cleanApproved;
        }

        if ($approvedByProducer === []) {
            $order->delete_meta_data(OrderMeta::PRODUCER_APPROVED_ATTENDEES);
        } else {
            $order->update_meta_data(OrderMeta::PRODUCER_APPROVED_ATTENDEES, $approvedByProducer);
        }

        $partialByProducer = $this->normalizeProducerPartialMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_MAP, true), $producerId);
        $cleanPartialMap   = $this->sanitizePartialMap($partialMap);

        if ($cleanPartialMap === []) {
            unset($partialByProducer[$producerId]);
        } else {
            $partialByProducer[$producerId] = $cleanPartialMap;
        }

        $this->saveProducerPartialMap($order, $partialByProducer);

        $totalByProducer = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_TOTAL, true), $producerId);
        $cleanTotal      = max(0.0, (float) $partialTotal);
        if ($cleanTotal <= 0.0) {
            unset($totalByProducer[$producerId]);
        } else {
            $totalByProducer[$producerId] = $cleanTotal;
        }

        $this->saveProducerFloatMap($order, OrderMeta::PARTIAL_APPROVED_TOTAL, $totalByProducer);
    }

    /**
     * @param array<int,int> $partialMap
     * @return array<int,int>
     */
    private function sanitizePartialMap(array $partialMap): array
    {
        $clean = [];

        foreach ($partialMap as $itemId => $count) {
            $itemKey  = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }
            $clean[$itemKey] = $intCount;
        }

        return $clean;
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,int>>
     */
    private function normalizeApprovedMetaByProducer($raw, ?int $producerId = null): array
    {
        return ProducerApprovalStore::normalizeApprovedMetaByProducer($raw, $producerId);
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,int>>
     */
    private function normalizeProducerPartialMap($raw, ?int $producerId = null): array
    {
        return ProducerApprovalStore::normalizeProducerPartialMap($raw, $producerId);
    }

    private function saveProducerPartialMap(WC_Order $order, array $map): void
    {
        if ($map === []) {
            $order->delete_meta_data(OrderMeta::PARTIAL_APPROVED_MAP);
            return;
        }

        foreach ($map as $producerId => $partialMap) {
            if (!is_array($partialMap) || $partialMap === []) {
                unset($map[$producerId]);
            }
        }

        if ($map === []) {
            $order->delete_meta_data(OrderMeta::PARTIAL_APPROVED_MAP);
            return;
        }

        $order->update_meta_data(OrderMeta::PARTIAL_APPROVED_MAP, $map);
    }

    /**
     * @param mixed $raw
     * @return array<int,float>
     */
    private function normalizeProducerFloatMap($raw, ?int $producerId = null): array
    {
        return ProducerApprovalStore::normalizeProducerFloatMap($raw, $producerId);
    }

    private function saveProducerFloatMap(WC_Order $order, string $key, array $map): void
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

    private function resolveProducerPartialTotal(WC_Order $order, int $producerId): float
    {
        $totals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_APPROVED_TOTAL, true), $producerId);
        if ($producerId > 0 && isset($totals[$producerId])) {
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

    private function resolveProducerCapturedTotal(WC_Order $order, int $producerId): float
    {
        $totals = $this->normalizeProducerFloatMap($order->get_meta(OrderMeta::PARTIAL_CAPTURED_TOTAL, true), $producerId);
        if ($producerId > 0 && isset($totals[$producerId])) {
            return $totals[$producerId];
        }

        if (isset($totals[self::LEGACY_PRODUCER_ID])) {
            return $totals[self::LEGACY_PRODUCER_ID];
        }

        return 0.0;
    }

    /**
     * @return array<int,array<int,array<int,int>>>
     */
    private function sanitizeAttendeeSelection(array $raw): array
    {
        $result = [];

        foreach ($raw as $orderId => $items) {
            $orderKey = (int) $orderId;
            if ($orderKey <= 0 || !is_array($items)) {
                continue;
            }

            $itemMap = [];
            foreach ($items as $itemId => $indices) {
                $itemKey = (int) $itemId;
                if ($itemKey <= 0) {
                    continue;
                }

                $values   = is_array($indices) ? $indices : [$indices];
                $filtered = $this->filterIndices($values, PHP_INT_MAX);
                if ($filtered === []) {
                    continue;
                }

                $itemMap[$itemKey] = $filtered;
            }

            if ($itemMap === []) {
                continue;
            }

            $result[$orderKey] = $itemMap;
        }

        return $result;
    }

    /**
     * @param array<int,int|string> $indices
     * @return array<int,int>
     */
    private function filterIndices(array $indices, int $limit): array
    {
        return ProducerApprovalStore::filterIndices($indices, $limit);
    }

    /**
     * @param array<string|int,mixed> $meta
     * @return array<int,array<int,int>>
     */
    private function normalizeApprovedMeta(array $meta): array
    {
        $result = [];
        foreach ($meta as $itemId => $indices) {
            $itemKey = (int) $itemId;
            if ($itemKey <= 0 || !is_array($indices)) {
                continue;
            }

            $result[$itemKey] = $this->filterIndices($indices, PHP_INT_MAX);
        }

        return $result;
    }

    /**
     * @param array<string|int,mixed> $map
     * @return array<int,int>
     */
    private function normalizePartialMap(array $map): array
    {
        $result = [];
        foreach ($map as $itemId => $count) {
            $itemKey  = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }

            $result[$itemKey] = $intCount;
        }

        return $result;
    }

    private function isProducerLineItem($item, int $producerId): bool
    {
        return Orders::isProducerLineItem($item, $producerId);
    }
}
