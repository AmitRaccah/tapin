<?php

namespace Tapin\Events\Integrations;

use Tapin\Events\Core\Service;

final class SmartSliderCache implements Service {
    public function register(): void {
        add_action('set_user_role', [$this,'clearAll'],20,3);
        add_action('add_user_role', [$this,'clearAll'],20,2);
        add_action('remove_user_role', [$this,'clearAll'],20,2);
        add_action('save_post_product', [$this,'clearAllOnSave'], 20, 3);
        add_action('transition_post_status', function($new,$old,$post){ if($post instanceof \WP_Post && $post->post_type==='product' && $new!==$old) $this->clearAll(0,'',[]); },20,3);
        foreach (['trashed_post','untrashed_post','before_delete_post'] as $h) {
            add_action($h, function($post_id){ if (get_post_type($post_id)==='product') $this->clearAll(0,'',[]); },20);
        }
        add_action('set_object_terms', function($object_id,$terms,$tt_ids,$taxonomy,$append,$old){ if($taxonomy==='product_cat' && get_post_type($object_id)==='product') $this->clearAll(0,'',[]); },20,6);
        add_action('tapin_ss3_clear_cache', [self::class, 'clearStatically'], 10, 0);
    }

    public function clearAllOnSave($post_id, $post, $update): void {
        self::runClear();
    }

    public function clearAll($user_id, $role, $old=[]): void {
        self::runClear();
    }

    public static function clearStatically(): void
    {
        self::runClear();
    }

    private static function runClear(): void
    {
        // plugin-level
        try {
            if (class_exists('\\Nextend\\SmartSlider3\\Platform\\WordPress\\Plugin') &&
                method_exists('\\Nextend\\SmartSlider3\\Platform\\WordPress\\Plugin', 'clearCache')) {
                \Nextend\SmartSlider3\Platform\WordPress\Plugin::clearCache();
            }
        } catch (\Throwable $e) {}

        if (!class_exists('\\Nextend\\SmartSlider3\\PublicApi\\Project')) return;
        global $wpdb;
        $tables = [$wpdb->prefix.'nextend2_smartslider3_sliders',$wpdb->prefix.'n2_ss3_sliders',$wpdb->prefix.'nextend_smartslider3_sliders'];
        $ids=[];
        foreach ($tables as $t){
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
            if ($exists === $t) $ids = array_merge($ids, array_map('intval', (array)$wpdb->get_col("SELECT id FROM {$t}")));
        }
        $ids = array_unique(array_filter($ids));
        foreach ($ids as $pid){ try{ \Nextend\SmartSlider3\PublicApi\Project::clearCache((int)$pid); }catch(\Throwable $e){} }
    }
}
