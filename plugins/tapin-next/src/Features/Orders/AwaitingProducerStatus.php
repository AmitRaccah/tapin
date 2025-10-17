<?php

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class AwaitingProducerStatus implements Service
{
    private const STATUS_KEY = 'wc-awaiting-producer';

    public function register(): void
    {
        add_action('init', [$this, 'registerStatus']);
        add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'registerForWooCommerce']);
        add_filter('wc_order_statuses', [$this, 'injectIntoList']);
        add_filter('bulk_actions-edit-shop_order', [$this, 'bulkAction']);
        add_filter('woocommerce_order_actions', [$this, 'orderAction']);
        add_action('woocommerce_order_action_mark_awaiting-producer', [$this, 'markAction']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'reportsStatuses']);
    }

    public function registerStatus(): void
    {
        register_post_status(self::STATUS_KEY, [
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

    public function registerForWooCommerce(array $statuses): array
    {
        $statuses[self::STATUS_KEY] = [
            'label'                     => 'ממתין לאישור מפיק',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ];

        return $statuses;
    }

    public function injectIntoList(array $statuses): array
    {
        $result = [];
        foreach ($statuses as $key => $label) {
            $result[$key] = $label;
            if ($key === 'wc-on-hold') {
                $result[self::STATUS_KEY] = 'ממתין לאישור מפיק';
            }
        }

        if (!isset($result[self::STATUS_KEY])) {
            $result[self::STATUS_KEY] = 'ממתין לאישור מפיק';
        }

        return $result;
    }

    public function bulkAction(array $actions): array
    {
        $actions['mark_awaiting-producer'] = 'סמן כממתין לאישור מפיק';
        return $actions;
    }

    public function orderAction(array $actions): array
    {
        $actions['mark_awaiting-producer'] = 'סמן כממתין לאישור מפיק';
        return $actions;
    }

    /**
     * @param int|\WC_Order $order
     */
    public function markAction($order): void
    {
        $wcOrder = is_numeric($order) ? wc_get_order($order) : $order;
        if ($wcOrder instanceof WC_Order) {
            $wcOrder->update_status('awaiting-producer');
        }
    }

    /**
     * @param array<int,string>|string $statuses
     * @return array<int,string>
     */
    public function reportsStatuses($statuses): array
    {
        $list = is_array($statuses) ? $statuses : (array) $statuses;
        $list[] = 'awaiting-producer';
        return array_values(array_unique($list));
    }
}
