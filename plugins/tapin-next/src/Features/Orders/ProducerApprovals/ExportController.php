<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Support\Security;
use WP_User;

final class ExportController
{
    public function handle(): void
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            return;
        }

        $guard = Security::producer();
        if (!$guard->allowed) {
            $message = $guard->message !== '' ? $guard->message : \Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1503;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#46;');
            status_header(403);
            wp_die(wp_kses_post($message));
        }

        $viewer = $guard->user instanceof WP_User ? $guard->user : wp_get_current_user();
        if (!SecurityHelpers::canDownloadOrders($viewer instanceof WP_User ? $viewer : null)) {
            status_header(403);
            wp_die(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1503;&#32;&#1500;&#1495;&#32;&#1492;&#1512;&#1513;&#1488;&#1492;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#32;&#1489;&#1511;&#1513;&#1493;&#1514;.'));
        }

        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $nonce   = isset($_GET['tapin_pa_export_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['tapin_pa_export_nonce'])) : '';

        if ($eventId <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'tapin_pa_export_event_' . $eventId)) {
            status_header(400);
            wp_die(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1490;&#1512;&#32;&#1488;&#1508;&#1512;&#1493;&#1497;&#32;&#1500;&#1488;&#1513;&#1512;.'));
        }

        $producerId = $viewer instanceof WP_User ? (int) $viewer->ID : (int) get_current_user_id();

        $ordersQuery = new OrderQuery();
        $orderSets   = $ordersQuery->resolveProducerOrderIds($producerId);
        $displayIds  = $orderSets['display'];

        if ($displayIds === []) {
            status_header(404);
            wp_die(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1513;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#46;'));
        }

        $summary       = new OrderSummaryBuilder();
        $collections   = $summary->summarizeOrders($displayIds, $producerId);
        $events        = (new EventGrouper())->group($collections['orders']);
        $targetEvent   = null;

        foreach ($events as $event) {
            if ((int) ($event['id'] ?? 0) === $eventId) {
                $targetEvent = $event;
                break;
            }
        }

        if ($targetEvent === null) {
            status_header(404);
            wp_die(\Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html::decodeEntities('&#1488;&#1497;&#1513;&#32;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1500;&#1492;&#1493;&#1512;&#1491;&#46;'));
        }

        $rows = (new ExportCsvBuilder())->build($targetEvent);
        (new ExportStreamer())->stream($targetEvent, $rows);
    }
}

