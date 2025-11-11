<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\ProducerApprovals\Assets as ProducerApprovalsAssets;
use Tapin\Events\Features\Sales\SalesAggregator;
use Tapin\Events\Features\Sales\SalesQuery;
use Tapin\Events\Features\Sales\SalesRenderer;

final class ProducerEventSales implements Service {
    private const TEXT = [
        'page_heading'        => "\u{05D3}\u{05D5}\u{05D7} \u{05DE}\u{05DB}\u{05D9}\u{05E8}\u{05D5}\u{05EA}",
        'regular_heading'     => "\u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD} \u{05E8}\u{05D2}\u{05D9}\u{05DC}\u{05D9}\u{05DD}",
        'regular_total'       => "\u{05E1}\u{05D4}\u{0022}\u{05DB} \u{05E0}\u{05DE}\u{05DB}\u{05E8}\u{05D5}",
        'from_link'           => "\u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'not_from_link'       => "\u{05DC}\u{05D0} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'affiliate_fee'       => "\u{05E2}\u{05DE}\u{05DC}\u{05EA} \u{05E9}\u{05D5}\u{05EA}\u{05E4}\u{05D9}\u{05DD}",
        'special_heading'     => "\u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD} \u{05DE}\u{05D9}\u{05D5}\u{05D7}\u{05D3}\u{05D9}\u{05DD}",
        'special_empty'       => "\u{05DC}\u{05D0} \u{05E0}\u{05DE}\u{05DB}\u{05E8}\u{05D5} \u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD} \u{05DE}\u{05D9}\u{05D5}\u{05D7}\u{05D3}\u{05D9}\u{05DD}",
        'windows_heading'     => "\u{05D7}\u{05DC}\u{05D5}\u{05E0}\u{05D5}\u{05EA} \u{05D4}\u{05E0}\u{05D7}\u{05D4}",
        'windows_empty'       => "\u{05D0}\u{05D9}\u{05DF} \u{05D7}\u{05DC}\u{05D5}\u{05E0}\u{05D5}\u{05EA} \u{05D4}\u{05E0}\u{05D7}\u{05D4} \u{05DC}\u{05D0}\u{05D9}\u{05E8}\u{05D5}\u{05E2} \u{05D4}\u{05D6}\u{05D4}",
        'total_tickets'       => "\u{05E1}\u{05D4}\u{0022}\u{05DB} \u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD}",
        'total_revenue'       => "\u{05E1}\u{05D4}\u{0022}\u{05DB} \u{05D4}\u{05DB}\u{05E0}\u{05E1}\u{05D5}\u{05EA}",
        'regular_link'        => "\u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD} \u{05E8}\u{05D2}\u{05D9}\u{05DC}\u{05D9}\u{05DD} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'regular_not_link'    => "\u{05DB}\u{05E8}\u{05D8}\u{05D9}\u{05E1}\u{05D9}\u{05DD} \u{05E8}\u{05D2}\u{05D9}\u{05DC}\u{05D9}\u{05DD} \u{05DC}\u{05D0} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'empty_state'         => "\u{05D0}\u{05D9}\u{05DF} \u{05DE}\u{05DB}\u{05D9}\u{05E8}\u{05D5}\u{05EA} \u{05DC}\u{05D4}\u{05E6}\u{05D2}\u{05D4}",
        'range_from'          => "\u{05DE}\u{002D}",
        'range_to'            => "\u{05E2}\u{05D3}",
        'window_single'       => "\u{05D7}\u{05DC}\u{05D5}\u{05DF}",
        'amounts_heading'     => "\u{05E1}\u{05DB}\u{05D5}\u{05DD} \u{05DB}\u{05DC}\u{05DC}\u{05D9}\u{05DD}",
        'sum_total'           => "\u{05E1}\u{05DB}\u{05D5}\u{05DD} \u{05DB}\u{05D5}\u{05DC}\u{05DC}",
        'sum_link'            => "\u{05E1}\u{05DB}\u{05D5}\u{05DD} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'sum_direct'          => "\u{05E1}\u{05DB}\u{05D5}\u{05DD} \u{05DC}\u{05D0} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'sum_commission_link' => "\u{05E2}\u{05DE}\u{05DC}\u{05D4} \u{05DE}\u{05D4}\u{05DC}\u{05D9}\u{05E0}\u{05E7}",
        'producer_commission' => "\u{05E2}\u{05DE}\u{05DC}\u{05EA} \u{05DE}\u{05E4}\u{05D9}\u{05E7}",
        'producer_commission_percent' => "\u{05D0}\u{05D7}\u{05D5}\u{05D6}\u{05D9}\u{05DD}",
        'producer_commission_flat'    => "\u{05E9}\u{05E7}\u{05DC}\u{05D9}\u{05DD}",
        'producer_commission_none'    => "\u{05DC}\u{05D0} \u{05D4}\u{05D5}\u{05D2}\u{05D3}\u{05D4} \u{05E2}\u{05DE}\u{05DC}\u{05D4}",
    ];
    public function register(): void { add_shortcode('producer_event_sales', [$this,'render']); }

    public function render($atts): string {
        if (!function_exists('wc_get_orders')) return '<p>WooCommerce נדרש.</p>';
        $a = shortcode_atts([
            'vendor'=>'current','from'=>'','to'=>'','statuses'=>'processing,completed','include_zero'=>'1','product_status'=>'publish'
        ], $atts, 'producer_event_sales');

        if (!is_user_logged_in()) { status_header(403); return '<div style="direction:rtl;text-align:right;background:#fff4f4;border:1px solid #f3c2c2;padding:12px;border-radius:8px">הדף זמין למשתמשים מורשים בלבד. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחבר/י</a>.</div>'; }
        $me = wp_get_current_user();
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_woocommerce');
        $role_ok  = array_intersect((array)$me->roles, ['producer','owner']);
        if (!$is_admin && empty($role_ok)) { status_header(403); return '<div style="direction:rtl;text-align:right;background:#fff4f4;border:1px solid #f3c2c2;padding:12px;border-radius:8px">אין לך הרשאה לצפות בדף זה.</div>'; }
        $can_view_all = $is_admin || in_array('owner', (array)$me->roles, true);

        $vendor_id = 0;
        if ($a['vendor']==='current') $vendor_id = $current_user_id;
        elseif (ctype_digit((string)$a['vendor'])) $vendor_id = (int)$a['vendor'];
        else { $u = get_user_by('slug', sanitize_title($a['vendor'])) ?: get_user_by('login', sanitize_user($a['vendor'])); if($u) $vendor_id=(int)$u->ID; }
        if (!$vendor_id) return '<p>לא נמצא מפיק.</p>';
        if (!$can_view_all && $current_user_id !== $vendor_id) { status_header(403); return '<p style="direction:rtl;text-align:right">אין לך הרשאה לצפות בדוח של משתמש אחר.</p>'; }

        ProducerApprovalsAssets::enqueue();
        $query = new SalesQuery();
        $order_ids = $query->resolveOrderIds([
            'from'     => (string) $a['from'],
            'to'       => (string) $a['to'],
            'statuses' => (string) $a['statuses'],
        ]);

        $aggregator = new SalesAggregator();
        $rows = $aggregator->aggregate(
            $order_ids,
            $vendor_id,
            $current_user_id,
            [
                'include_zero'   => (int) $a['include_zero'] === 1,
                'product_status' => (string) $a['product_status'],
            ]
        );

        $renderer = new SalesRenderer();
        return $renderer->render($rows, $current_user_id, self::TEXT);
    }
}
