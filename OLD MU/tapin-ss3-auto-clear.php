<?php
if (defined('WP_INSTALLING') && WP_INSTALLING) return;




if (!function_exists('tapin_ss3_clear_all_sliders_cache')) {
    function tapin_ss3_clear_all_sliders_cache(){
        try {
            if (class_exists('\\Nextend\\SmartSlider3\\Platform\\WordPress\\Plugin') &&
                method_exists('\\Nextend\\SmartSlider3\\Platform\\WordPress\\Plugin', 'clearCache')) {
                \Nextend\SmartSlider3\Platform\WordPress\Plugin::clearCache();
            }
        } catch (\Throwable $e) {}

        if (!class_exists('\\Nextend\\SmartSlider3\\PublicApi\\Project')) return;

        global $wpdb;
        $tables = [
            $wpdb->prefix . 'nextend2_smartslider3_sliders',
            $wpdb->prefix . 'n2_ss3_sliders',
            $wpdb->prefix . 'nextend_smartslider3_sliders',
        ];

        $ids = [];
        foreach ($tables as $t) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
            if ($exists === $t) {
                $ids = array_merge($ids, array_map('intval', (array)$wpdb->get_col("SELECT id FROM {$t}")));
            }
        }
        $ids = array_unique(array_filter($ids));
        if (!$ids) return;

        foreach ($ids as $pid) {
            try { \Nextend\SmartSlider3\PublicApi\Project::clearCache((int)$pid); } catch (\Throwable $e) {}
        }
    }
}


add_action('set_user_role', function($user_id, $role, $old_roles){
    tapin_ss3_clear_all_sliders_cache();
}, 20, 3);

add_action('add_user_role', function($user_id, $role){
    tapin_ss3_clear_all_sliders_cache();
}, 20, 2);

add_action('remove_user_role', function($user_id, $role){
    tapin_ss3_clear_all_sliders_cache();
}, 20, 2);


add_action('transition_post_status', function($new_status, $old_status, $post){
    if ($post instanceof WP_Post && $post->post_type === 'product' && $new_status !== $old_status) {
        tapin_ss3_clear_all_sliders_cache();
    }
}, 20, 3);


add_action('trashed_post', function($post_id){
    if (get_post_type($post_id) === 'product') {
        tapin_ss3_clear_all_sliders_cache();
    }
}, 20);


add_action('untrashed_post', function($post_id){
    if (get_post_type($post_id) === 'product') {
        tapin_ss3_clear_all_sliders_cache();
    }
}, 20);


add_action('before_delete_post', function($post_id){
    if (get_post_type($post_id) === 'product') {
        tapin_ss3_clear_all_sliders_cache();
    }
}, 20);


add_action('set_object_terms', function($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids){
    if ($taxonomy === 'product_cat' && get_post_type($object_id) === 'product') {
        tapin_ss3_clear_all_sliders_cache();
    }
}, 20, 6);
