<?php
namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class OrderStatus implements Service {
    public function register(): void {
        add_action('init', [$this,'registerStatus']);
        add_filter('wc_order_statuses', [$this,'addToList']);
    }

    public function registerStatus(): void {
        register_post_status('wc-awaiting-producer', [
            'label' => 'ממתין לאישור מפיק',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'ממתין לאישור מפיק <span class="count">(%s)</span>',
                'ממתין לאישור מפיק <span class="count">(%s)</span>',
                'tapin-events'
            ),
        ]);
    }

    public function addToList(array $statuses): array {
        $out = [];
        foreach ($statuses as $k => $v) {
            $out[$k] = $v;
            if ($k === 'wc-pending') $out['wc-awaiting-producer'] = 'ממתין לאישור מפיק';
        }
        if (!isset($out['wc-awaiting-producer'])) {
            $out['wc-awaiting-producer'] = 'ממתין לאישור מפיק';
        }
        return $out;
    }
}
