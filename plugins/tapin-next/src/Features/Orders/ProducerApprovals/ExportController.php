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
            $message = $guard->message !== '' ? $guard->message : Security::forbiddenMessage(__('אין הרשאה.', 'tapin'));
            status_header(403);
            wp_die(wp_kses_post($message));
        }

        $viewer = $guard->user instanceof WP_User ? $guard->user : wp_get_current_user();
        if (!SecurityHelpers::canDownloadOrders($viewer instanceof WP_User ? $viewer : null)) {
            status_header(403);
            wp_die(esc_html__('אין לך הרשאה להוריד קובץ זה.', 'tapin'));
        }

        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $nonce   = isset($_GET['tapin_pa_export_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['tapin_pa_export_nonce'])) : '';

        if ($eventId <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'tapin_pa_export_event_' . $eventId)) {
            status_header(400);
            wp_die(esc_html__('הבקשה אינה תקינה.', 'tapin'));
        }

        $producerId = $viewer instanceof WP_User ? (int) $viewer->ID : (int) get_current_user_id();

        $ordersQuery = new OrderQuery();
        $orderSets   = $ordersQuery->resolveProducerOrderIds($producerId);
        $displayIds  = $orderSets['display'];

        if ($displayIds === []) {
            status_header(404);
            wp_die(esc_html__('לא נמצא אירוע להורדה.', 'tapin'));
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
            wp_die(esc_html__('לא נמצא אירוע להורדה.', 'tapin'));
        }

        $rows = (new ExportCsvBuilder())->build($targetEvent);
        (new ExportStreamer())->stream($targetEvent, $rows);
    }
}

