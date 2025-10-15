<?php
namespace Tapin\Events\Support;

final class Cap {
    /**
     * מנהל/ת מערכת מרכז הניהול?
     * מאפשר: administrator, shop_manager, owner או כל מי שיש לו manage_woocommerce / manage_options
     * אפשר להרחיב בפילטר: tapin_events_is_manager
     */
    public static function isManager(): bool {
        if (!is_user_logged_in()) return false;
        $u = wp_get_current_user(); if (!$u) return false;

        $roles = (array) $u->roles;
        if (array_intersect($roles, ['administrator', 'shop_manager', 'owner'])) return true;

        if (current_user_can('manage_woocommerce') || current_user_can('manage_options')) return true;

        /** מאפשר הרחבה חיצונית */
        return (bool) apply_filters('tapin_events_is_manager', false, $u);
    }
}
