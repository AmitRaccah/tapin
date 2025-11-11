<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html;
use WC_Order;

final class PartiallyApprovedStatus implements Service
{
    public const STATUS_KEY   = 'wc-partially-appr';
    public const STATUS_SLUG  = 'partially-appr';
    public const STATUS_LABEL = '&#1488;&#1493;&#1513;&#1512;&#32;&#1495;&#1500;&#1511;&#1497;&#1514;';

    private const ACTION_KEY   = 'mark_partially-appr';
    private const ACTION_LABEL = '&#1505;&#1502;&#1503;&#32;&#1499;&#1488;&#1493;&#1513;&#1512;&#32;&#1495;&#1500;&#1511;&#1497;&#1514;';

    public function register(): void
    {
        add_action('init', [$this, 'registerStatus']);
        add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'registerForWooCommerce']);
        add_filter('wc_order_statuses', [$this, 'injectIntoList']);
        add_filter('bulk_actions-edit-shop_order', [$this, 'bulkAction']);
        add_filter('woocommerce_order_actions', [$this, 'orderAction']);
        add_action('woocommerce_order_action_' . self::ACTION_KEY, [$this, 'markAction']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'reportsStatuses']);
    }

    public function registerStatus(): void
    {
        register_post_status(self::STATUS_KEY, $this->statusArgs());
    }

    public function registerForWooCommerce(array $statuses): array
    {
        $statuses[self::STATUS_KEY] = $this->statusArgs(false);

        return $statuses;
    }

    public function injectIntoList(array $statuses): array
    {
        $result = [];
        foreach ($statuses as $key => $label) {
            $result[$key] = $label;
            if ($key === AwaitingProducerStatus::STATUS_KEY) {
                $result[self::STATUS_KEY] = $this->label();
            }
        }

        if (!isset($result[self::STATUS_KEY])) {
            $result[self::STATUS_KEY] = $this->label();
        }

        return $result;
    }

    public function bulkAction(array $actions): array
    {
        return $this->injectAction($actions);
    }

    public function orderAction(array $actions): array
    {
        return $this->injectAction($actions);
    }

    /**
     * @param int|WC_Order $order
     */
    public function markAction($order): void
    {
        $wcOrder = is_numeric($order) ? wc_get_order((int) $order) : $order;
        if ($wcOrder instanceof WC_Order) {
            $wcOrder->update_status(self::STATUS_SLUG);
        }
    }

    /**
     * @param array<int,string>|string $statuses
     * @return array<int,string>
     */
    public function reportsStatuses($statuses): array
    {
        $list   = is_array($statuses) ? $statuses : (array) $statuses;
        $list[] = self::STATUS_SLUG;

        return array_values(array_unique($list));
    }

    private function statusArgs(bool $includeLabelCount = true): array
    {
        $args = [
            'label'                     => $this->label(),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ];

        if ($includeLabelCount) {
            $label               = $this->label() . ' <span class="count">(%s)</span>';
            $args['label_count'] = _n_noop($label, $label);
        }

        return $args;
    }

    private function injectAction(array $actions): array
    {
        $actions[self::ACTION_KEY] = $this->actionLabel();

        return $actions;
    }

    private function label(): string
    {
        return Html::decodeEntities(self::STATUS_LABEL);
    }

    private function actionLabel(): string
    {
        return Html::decodeEntities(self::ACTION_LABEL);
    }
}
