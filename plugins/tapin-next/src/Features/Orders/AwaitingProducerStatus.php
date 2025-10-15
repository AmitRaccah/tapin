<?php
namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

class AwaitingProducerStatus implements Service {
    public function register(): void {
        add_action('init', [$this,'registerStatus']);
        add_filter('woocommerce_register_shop_order_post_statuses', [$this,'registerForWC']);
        add_filter('wc_order_statuses', [$this,'injectIntoList']);
        add_filter('bulk_actions-edit-shop_order', [$this,'bulkAction']);
        add_filter('woocommerce_order_actions', [$this,'orderAction']);
        add_action('woocommerce_order_action_mark_awaiting-producer', [$this,'markAction']);
        add_filter('woocommerce_reports_order_statuses', [$this,'reportsStatuses']);
    }

    public function registerStatus(): void {
        register_post_status('wc-awaiting-producer', [
            'label'                     => 'ממתין לאישור מפיק',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'ממתין לאישור מפיק <span class="count">(%s)</span>',
                'ממתין לאישור מפיק <span class="count">(%s)</span>'
            ),
        ]);
    }

    public function registerForWC(array $st): array {
        $st['wc-awaiting-producer'] = [
            'label'                     => 'ממתין לאישור מפיק',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ];
        return $st;
    }

    public function injectIntoList(array $list): array {
        $out = [];
        foreach ($list as $k=>$v) {
            $out[$k] = $v;
            if ($k === 'wc-on-hold') $out['wc-awaiting-producer'] = 'ממתין לאישור מפיק';
        }
        if (!isset($out['wc-awaiting-producer'])) $out['wc-awaiting-producer'] = 'ממתין לאישור מפיק';
        return $out;
    }

    public function bulkAction(array $actions): array {
        $actions['mark_awaiting-producer'] = 'שנה ל–ממתין לאישור מפיק';
        return $actions;
    }

    public function orderAction(array $actions): array {
        $actions['mark_awaiting-producer'] = 'שנה ל–ממתין לאישור מפיק';
        return $actions;
    }

    public function markAction($order): void {
        $o = is_numeric($order) ? wc_get_order($order) : $order;
        if ($o) $o->update_status('awaiting-producer');
    }

    public function reportsStatuses($statuses) {
        $arr = is_array($statuses) ? $statuses : (array)$statuses;
        $arr[] = 'awaiting-producer';
        return array_values(array_unique($arr));
    }
}
