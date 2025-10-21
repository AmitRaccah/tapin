<?php

namespace Tapin\Events\Support;

use Tapin\Events\Core\Service;

final class Capabilities implements Service
{
    public const ATTENDEE_MANAGE_CAP = 'tapin_manage_attendees';

    public function register(): void
    {
        add_action('init', [$this, 'ensureCapabilities']);
    }

    public function ensureCapabilities(): void
    {
        if (!function_exists('get_role')) {
            return;
        }

        $roles = (array) apply_filters(
            'tapin_events_attendee_capability_roles',
            ['administrator', 'shop_manager', 'owner', 'producer']
        );

        foreach ($roles as $roleName) {
            $roleName = (string) $roleName;
            if ($roleName === '') {
                continue;
            }

            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            if (!$role->has_cap(self::ATTENDEE_MANAGE_CAP)) {
                $role->add_cap(self::ATTENDEE_MANAGE_CAP);
            }
        }
    }

    public static function attendeeCapability(): string
    {
        return self::ATTENDEE_MANAGE_CAP;
    }
}
