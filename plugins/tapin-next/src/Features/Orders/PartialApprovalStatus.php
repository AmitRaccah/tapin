<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class PartialApprovalStatus implements Service
{
    public const STATUS_KEY  = 'wc-partial-appr';
    public const STATUS_SLUG = 'partial-appr';

    private const STATUS_LABEL_ENT = '&#1488;&#1513;&#1512;&#32;&#1492;&#1495;&#1500;&#1511;&#1497;&#1514;';

    public function register(): void
    {
        add_action('init', [$this, 'registerStatus']);
        add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'registerForWooCommerce']);
        add_filter('wc_order_statuses', [$this, 'injectIntoList']);
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
            if ($key === 'wc-processing') {
                $result[self::STATUS_KEY] = $this->statusLabel();
            }
        }

        if (!isset($result[self::STATUS_KEY])) {
            $result[self::STATUS_KEY] = $this->statusLabel();
        }

        return $result;
    }

    /**
     * @param array<int,string>|string $statuses
     * @return array<int,string>
     */
    public function reportsStatuses($statuses): array
    {
        $list = is_array($statuses) ? $statuses : (array) $statuses;
        $list[] = self::STATUS_SLUG;
        return array_values(array_unique($list));
    }

    private function statusArgs(bool $includeLabelCount = true): array
    {
        $label = $this->statusLabel();

        $args = [
            'label'                     => $label,
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ];

        if ($includeLabelCount) {
            $countLabel = $label . ' <span class="count">(%s)</span>';
            $args['label_count'] = _n_noop($countLabel, $countLabel);
        }

        return $args;
    }

    private function statusLabel(): string
    {
        return html_entity_decode(self::STATUS_LABEL_ENT, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
