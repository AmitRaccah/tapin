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
            return '<div class="woocommerce-info" style="direction:rtl;text-align:right">יש להתחבר כדי לצפות בהזמנות.</div>';
        }

        $guard = Security::producer();
        if (!$guard->allowed) {
            return $guard->message !== ''
                ? $guard->message
                : '<div class="woocommerce-error" style="direction:rtl;text-align:right">אין לך הרשאה לצפות בעמוד זה.</div>';
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
        $collections = $summary->summarizeOrders($displayIds, $producerId);
        $orders      = $collections['orders'];

        $eventWarnings = (new CustomerWarningsService())->buildEventOrderWarnings($orders);
        $events        = (new EventGrouper())->group($orders, $eventWarnings);

        Assets::enqueue();

        return (new Renderer())->render([
            'events' => $events,
            'can_download_export' => $canDownloadExport,
        ], $notice);
    }
}

