<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Support\Security;
use WP_User;

final class ShortcodeController
{
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="woocommerce-info" style="direction:rtl;text-align:right">&#1497;&#1513;&#32;&#1500;&#1492;&#1510;&#1495;&#1489;&#1512;&#32;&#1499;&#1491;&#1497;&#32;&#1500;&#1510;&#1508;&#1493;&#1514;&#32;&#1489;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;.</div>';
        }

        $guard = Security::producer();
        if (!$guard->allowed) {
            return $guard->message !== ''
                ? $guard->message
                : '<div class="woocommerce-error" style="direction:rtl;text-align:right">&#1488;&#1497;&#1503;&#32;&#1500;&#1495;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#32;&#1500;&#1510;&#1508;&#1493;&#1514;&#32;&#1489;&#1506;&#1502;&#1493;&#1491;&#32;&#1494;&#1492;.</div>';
        }

        $viewer     = $guard->user instanceof WP_User ? $guard->user : wp_get_current_user();
        $producerId = $viewer instanceof WP_User ? (int) $viewer->ID : (int) get_current_user_id();

        $canDownloadExport = SecurityHelpers::canDownloadOrders($viewer instanceof WP_User ? $viewer : null);

        $ordersQuery = new OrderQuery();
        $orderSets   = $ordersQuery->resolveProducerOrderIds($producerId);
        $relevantIds = $orderSets['relevant'];
        $displayIds  = $orderSets['display'];

        $notice = '';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            $bulk = new BulkActionsController();
            $res  = $bulk->handle($relevantIds);
            $notice = (string) ($res['notice'] ?? '');

            $orderSets   = $ordersQuery->resolveProducerOrderIds($producerId);
            $relevantIds = $orderSets['relevant'];
            $displayIds  = $orderSets['display'];
        }

        $summary = new OrderSummaryBuilder();
        $collections   = $summary->summarizeOrders($displayIds, $producerId);
        $orders        = $collections['orders'];
        $customerStats = $collections['customer_stats'];

        $warnings = (new CustomerWarningsService())->buildWarnings($customerStats);
        $events   = (new EventGrouper())->group($orders, $warnings);

        Assets::enqueue();

        return (new Renderer())->render([
            'events' => $events,
            'can_download_export' => $canDownloadExport,
        ], $notice);
    }
}

