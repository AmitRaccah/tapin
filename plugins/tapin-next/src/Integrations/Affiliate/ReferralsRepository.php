<?php
declare(strict_types=1);

namespace Tapin\Events\Integrations\Affiliate;

use wpdb;

final class ReferralsRepository
{
    /**
     * @param array<int,bool> $cache
     */
    public function hasReferral(int $orderId, int $affiliateId, array &$cache): bool
    {
        if ($orderId <= 0 || $affiliateId <= 0 || !function_exists('afwc_get_product_affiliate_url')) {
            return false;
        }

        if (array_key_exists($orderId, $cache)) {
            return (bool) $cache[$orderId];
        }

        global $wpdb;
        if (!$wpdb instanceof wpdb) {
            $cache[$orderId] = false;
            return false;
        }

        $table = $wpdb->prefix . 'afwc_referrals';
        $cache[$orderId] = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT referral_id FROM {$table} WHERE post_id = %d AND affiliate_id = %d AND status <> %s LIMIT 1",
                $orderId,
                $affiliateId,
                'rejected'
            )
        );

        return $cache[$orderId];
    }
}
