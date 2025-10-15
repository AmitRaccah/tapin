<?php
namespace Tapin\Events\Support;

use Tapin\Events\Core\Service;

final class Compat implements Service {
    public function register(): void {
        // מבטיח ש־do_shortcode ירוץ על התוכן ועל ווידג'טים טקסטואליים
        add_filter('the_content', 'do_shortcode', 11);
        add_filter('widget_text', 'do_shortcode');

        // תיקון [[shortcode]] -> [shortcode] + מרכאות חכמות
        add_filter('the_content', [$this,'normalizeShortcodes'], 9);

        // כלי דיבוג: /wp-admin/?tapin_sc_debug=1 — מציג אם השורטקודים רשומים
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) return;
            if (empty($_GET['tapin_sc_debug'])) return;
            $tags = ['events_admin_center','producer_event_request','producer_order_approvals','producer_event_sales'];
            $rows = array_map(fn($t)=> sprintf('<li>%s: <strong>%s</strong></li>', esc_html($t), shortcode_exists($t)?'YES':'NO'), $tags);
            echo '<div class="notice notice-info"><p>Tapin Shortcodes:</p><ul>'.implode('', $rows).'</ul></div>';
        });
    }

    public function normalizeShortcodes(string $content): string {
        // המרת מרכאות “חכמות” לרגילות
        $content = strtr($content, [
            "“" => '"', "”" => '"', "„" => '"', "«" => '"', "»" => '"',
            "‘" => "'", "’" => "'",
        ]);

        // [[events_admin_center ...]] => [events_admin_center ...] (רק לשורטקודים שלנו)
        $pattern = '/\[\[(\s*(?:events_admin_center|producer_event_request|producer_order_approvals|producer_event_sales)\b[^\]]*)\]\]/i';
        $content = preg_replace($pattern, '[$1]', $content);

        return $content;
    }
}
