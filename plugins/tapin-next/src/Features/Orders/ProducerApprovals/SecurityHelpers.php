<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use WP_User;

final class SecurityHelpers
{
    public static function canDownloadOrders(?WP_User $user): bool
    {
        $allowed = self::isAdministrator($user);

        return (bool) apply_filters('tapin_events_can_download_producer_orders', $allowed, $user);
    }

    public static function isAdministrator(?WP_User $user): bool
    {
        if (!$user instanceof WP_User) {
            return false;
        }

        if (is_multisite() && is_super_admin((int) $user->ID)) {
            return true;
        }

        return in_array('administrator', (array) $user->roles, true);
    }
}

