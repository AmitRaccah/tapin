<?php

namespace Tapin\Events\Integrations\Affiliate;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\Commission;
use Tapin\Events\Support\MetaKeys;

final class AffiliateService implements Service
{
    public function register(): void
    {
        add_filter('afwc_conversion_data', [self::class, 'overrideProducerCommission'], 20);
        add_action('admin_init', [self::class, 'ensureProducerRoleAffiliate']);
    }

    public static function ensureProducerRoleAffiliate(): void
    {
        $roles = get_option('affiliate_users_roles', []);
        if (!is_array($roles)) {
            $roles = [];
        }

        if (in_array('producer', $roles, true)) {
            return;
        }

        $roles[] = 'producer';
        update_option('affiliate_users_roles', array_values(array_unique($roles)));
    }

    /**
     * @param array<string,mixed> $conversionData
     * @return array<string,mixed>
     */
    public static function overrideProducerCommission($conversionData)
    {
        if (!is_array($conversionData)) {
            return $conversionData;
        }

        $orderId = isset($conversionData['oid']) ? (int) $conversionData['oid'] : 0;
        $affiliateId = isset($conversionData['affiliate_id']) ? (int) $conversionData['affiliate_id'] : 0;

        if ($orderId <= 0 || $affiliateId <= 0) {
            return $conversionData;
        }

        if (!function_exists('wc_get_order')) {
            return $conversionData;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return $conversionData;
        }

        $sum = 0.0;

        foreach ($order->get_items('line_item') as $item) {
            if (!method_exists($item, 'get_product_id')) {
                continue;
            }

            $productId = (int) $item->get_product_id();
            if ($productId <= 0) {
                continue;
            }

            $authorId = (int) get_post_field('post_author', $productId);
            if ($authorId !== $affiliateId) {
                continue;
            }

            $type = get_post_meta($productId, MetaKeys::PRODUCER_AFF_TYPE, true);
            $amountValue = get_post_meta($productId, MetaKeys::PRODUCER_AFF_AMOUNT, true);
            $amount = is_numeric($amountValue) ? (float) $amountValue : 0.0;

            $meta = [
                'type'   => in_array($type, ['percent', 'flat'], true) ? (string) $type : '',
                'amount' => $amount,
            ];

            $lineTotal = (float) $item->get_total();
            $quantity = max(1, (int) $item->get_quantity());
            $commission = Commission::calculate($meta, $lineTotal, $quantity);

            if ($commission > 0) {
                $sum += $commission;
            }
        }

        if ($sum > 0) {
            $conversionData['amount'] = round($sum, 2);
        }

        return $conversionData;
    }
}
