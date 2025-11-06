<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use WC_Order;

final class BulkActionsController
{
    /**
     * @param array<int,int> $relevantIds
     * @return array{notice: string}
     */
    public function handle(array $relevantIds): array
    {
        $notice = '';

        if (
            'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')
            && !empty($_POST['tapin_pa_bulk_nonce'])
            && wp_verify_nonce($_POST['tapin_pa_bulk_nonce'], 'tapin_pa_bulk')
        ) {
            $approveAll     = !empty($_POST['approve_all']);
            $cancelSelected = isset($_POST['bulk_cancel']);
            $selected       = array_map('absint', (array) ($_POST['order_ids'] ?? []));

            if ($approveAll) {
                $selected = $relevantIds;
            }

            $approved = 0;
            $failed   = 0;

            foreach (array_unique($selected) as $orderId) {
                if (!in_array($orderId, $relevantIds, true)) {
                    $failed++;
                    continue;
                }

                $order = wc_get_order($orderId);
                if (!$order instanceof WC_Order || AwaitingProducerStatus::STATUS_SLUG !== $order->get_status()) {
                    $failed++;
                    continue;
                }

                if ($cancelSelected) {
                    $order->update_status('cancelled', '&#1492;&#1492;&#1494;&#1502;&#1504;&#1492;&#32;&#1489;&#1493;&#1496;&#1500;&#1488;&#32;&#1500;&#1489;&#1511;&#1513;&#1514;&#32;&#1492;&#1502;&#1508;&#1497;&#1511;.');
                    $approved++;
                } else {
                    AwaitingProducerGate::captureAndApprove($order);
                    $approved++;
                }
            }

            if ($approved || $failed) {
                $notice = sprintf(
                    '<div class="woocommerce-message" style="direction:rtl;text-align:right">&#1488;&#1493;&#1513;&#1512;&#1493;&#32;%1$d&#32;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;,&#32;&#1504;&#1499;&#1513;&#1500;&#1493;&#32;%2$d.</div>',
                    $approved,
                    $failed
                );
            }
        }

        return ['notice' => $notice];
    }
}

