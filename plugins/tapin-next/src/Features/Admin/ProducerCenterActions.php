<?php
namespace Tapin\Events\Features\Admin;

use Tapin\Events\Domain\EventProductService;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Support\MetaKeys;

final class ProducerCenterActions {
    public static function handle(): string {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return '';
        }
        if (empty($_POST['tapin_pe_nonce']) || !wp_verify_nonce($_POST['tapin_pe_nonce'], 'tapin_pe_action')) {
            return '';
        }
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $pid  = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        $post = $pid ? get_post($pid) : null;
        if (!$post || get_post_type($pid) !== 'product' || (int) $post->post_author !== (int) $user->ID) {
            return '';
        }

        foreach (['save_pending', 'request_edit', 'cancel_request'] as $action) {
            if (!isset($_POST[$action])) {
                continue;
            }
            return self::dispatch($action, $pid, $user);
        }

        return '';
    }

    private static function dispatch(string $action, int $pid, \WP_User $user): string {
        switch ($action) {
            case 'save_pending':
                return self::savePending($pid, $user);
            case 'request_edit':
                return self::requestEdit($pid, $user);
            case 'cancel_request':
                delete_post_meta($pid, MetaKeys::EDIT_REQ);
                return '<div class="tapin-notice tapin-notice--warning">בקשת העדכון בוטלה.</div>';
        }
        return '';
    }

    private static function savePending(int $pid, \WP_User $user): string {
        $lock = sprintf('tapin_edit_pending_%d_%d', $pid, $user->ID);
        if (get_transient($lock)) {
            return '<div class="tapin-notice tapin-notice--error">כבר התקבלה שמירה דומה. נסה שוב בעוד רגע.</div>';
        }
        set_transient($lock, 1, 5);

        $service = new EventProductService();
        $service->applyFields($pid, [
            'title'        => $_POST['title'] ?? '',
            'desc'         => $_POST['desc'] ?? '',
            'price'        => $_POST['price'] ?? '',
            'stock'        => $_POST['stock'] ?? '',
            'event_dt'     => $_POST['event_dt'] ?? '',
            'image_field'  => 'image',
            'sale_windows' => SaleWindowsRepository::parseFromPost('sale_w'),
        ]);

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($pid);
        }
        clean_post_cache($pid);

        return '<div class="tapin-notice tapin-notice--success">הטופס עודכן.</div>';
    }

    private static function requestEdit(int $pid, \WP_User $user): string {
        $lock = sprintf('tapin_editreq_%d_%d', $pid, $user->ID);
        if (get_transient($lock)) {
            return '<div class="tapin-notice tapin-notice--error">בקשה דומה כבר נשלחה.</div>';
        }
        set_transient($lock, 1, MINUTE_IN_SECONDS);

        $data = [
            'title'        => sanitize_text_field($_POST['title'] ?? ''),
            'desc'         => wp_kses_post($_POST['desc'] ?? ''),
            'price'        => $_POST['price'] ?? '',
            'stock'        => $_POST['stock'] ?? '',
            'event_dt'     => sanitize_text_field($_POST['event_dt'] ?? ''),
            'sale_windows' => SaleWindowsRepository::parseFromPost('sale_w'),
        ];

        if (!empty($_FILES['image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload('image', 0);
            if (!is_wp_error($attachment_id)) {
                $data['new_image_id'] = (int) $attachment_id;
            }
        }

        update_post_meta($pid, MetaKeys::EDIT_REQ, [
            'by'   => (int) $user->ID,
            'at'   => time(),
            'data' => $data,
        ]);

        return '<div class="tapin-notice tapin-notice--success">בקשת העדכון נשלחה למנהלי Tapin.</div>';
    }
}
