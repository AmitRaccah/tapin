<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\UI\Components\AffiliateLinkUI;

final class ProducerEventSales implements Service {
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

        $get_thumb = function($pid){
            $url = get_the_post_thumbnail_url($pid,'woocommerce_thumbnail');
            if (!$url && function_exists('wc_placeholder_img_src')) $url = wc_placeholder_img_src();
            if (!$url) $url = includes_url('images/media/default.png');
            return $url;
        };
        $event_ts_cache = [];
        $get_event_ts = static function(int $pid) use (&$event_ts_cache): int {
            if (!array_key_exists($pid, $event_ts_cache)) {
                $ts = 0;
                $raw = get_post_meta($pid, MetaKeys::EVENT_DATE, true);
                if ($raw) {
                    $maybe = strtotime($raw);
                    if ($maybe) {
                        $ts = $maybe;
                    }
                }
                if (!$ts) {
                    $post = get_post($pid);
                    if ($post instanceof \WP_Post) {
                        $ts = get_post_time('U', true, $pid) ?: strtotime($post->post_date_gmt ?: $post->post_date) ?: 0;
                    }
                }
                $event_ts_cache[$pid] = $ts ?: 0;
            }
            return $event_ts_cache[$pid];
        };

        $date_after  = $a['from'] ? date_i18n('Y-m-d 00:00:00', strtotime(sanitize_text_field($a['from']))) : '';
        $date_before = $a['to']   ? date_i18n('Y-m-d 23:59:59', strtotime(sanitize_text_field($a['to'])))   : '';
        $statuses    = array_filter(array_map(fn($s)=> 'wc-'.sanitize_key(trim($s)), explode(',', $a['statuses'])));

        $order_args = ['limit'=>-1,'type'=>'shop_order','status'=>$statuses?:['wc-processing','wc-completed'],'return'=>'ids'];
        if ($date_after || $date_before) {
            $order_args['date_created'] = array_filter(['after'=>$date_after?:null,'before'=>$date_before?:null,'inclusive'=>true]);
        }
        $order_ids = wc_get_orders($order_args);
        if (!is_array($order_ids)) {
            $order_ids = [];
        }

        $rows = [];
        $author_cache = [];
        $commission_meta = [];
        $affiliate_id = $current_user_id;
        $can_check_referrals = $affiliate_id > 0 && function_exists('afwc_get_product_affiliate_url');
        $referral_cache = [];

        $order_has_referral = static function(int $order_id) use (&$referral_cache, $affiliate_id, $can_check_referrals) {
            if (!$can_check_referrals || $order_id <= 0) {
                return false;
            }
            if (array_key_exists($order_id, $referral_cache)) {
                return $referral_cache[$order_id];
            }
            global $wpdb;
            $table = $wpdb->prefix . 'afwc_referrals';
            $referral_cache[$order_id] = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT referral_id FROM {$table} WHERE post_id = %d AND affiliate_id = %d AND status <> %s LIMIT 1",
                    $order_id,
                    $affiliate_id,
                    'rejected'
                )
            );
            return $referral_cache[$order_id];
        };

        $get_author = static function(int $pid) use (&$author_cache): int {
            if (!array_key_exists($pid, $author_cache)) {
                $author_cache[$pid] = (int) get_post_field('post_author', $pid);
            }
            return $author_cache[$pid];
        };

        $get_commission_meta = static function(int $pid) use (&$commission_meta): array {
            if (!array_key_exists($pid, $commission_meta)) {
                $type = get_post_meta($pid, MetaKeys::PRODUCER_AFF_TYPE, true);
                $amount = get_post_meta($pid, MetaKeys::PRODUCER_AFF_AMOUNT, true);
                $commission_meta[$pid] = [
                    'type' => in_array($type, ['percent','flat'], true) ? $type : '',
                    'amount' => is_numeric($amount) ? (float) $amount : 0.0,
                ];
            }
            return $commission_meta[$pid];
        };

        $calc_commission = static function(array $meta, float $line_total, int $quantity): float {
            if ($meta['amount'] <= 0) {
                return 0.0;
            }
            if ($meta['type'] === 'percent') {
                return $line_total > 0 ? ($line_total * $meta['amount']) / 100 : 0.0;
            }
            if ($meta['type'] === 'flat') {
                return $quantity > 0 ? $meta['amount'] * $quantity : 0.0;
            }
            return 0.0;
        };

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) {
                continue;
            }
            $was_referred = $order_has_referral((int) $oid);
            foreach ($order->get_items('line_item') as $item) {
                $pid = (int) $item->get_product_id();
                if (!$pid) {
                    continue;
                }
                $author = $get_author($pid);
                if ($author !== $vendor_id) {
                    continue;
                }
                if (!isset($rows[$pid])) {
                    $rows[$pid] = [
                        'name'=>get_the_title($pid),
                        'qty'=>0,
                        'sum'=>0.0,
                        'view'=>get_permalink($pid),
                        'thumb'=>$get_thumb($pid),
                        'author_id'=>$author,
                        'ref_qty'=>0,
                        'ref_sum'=>0.0,
                        'ref_commission'=>0.0,
                        'event_ts'=>$get_event_ts($pid),
                    ];
                }
                $qty = (int) $item->get_quantity();
                $line_total = (float) $item->get_total();
                $rows[$pid]['qty'] += $qty;
                $rows[$pid]['sum'] += $line_total;

                if ($was_referred) {
                    $rows[$pid]['ref_qty'] += $qty;
                    $rows[$pid]['ref_sum'] += $line_total;
                    $commission = $calc_commission($get_commission_meta($pid), $line_total, $qty);
                    if ($commission > 0) {
                        $rows[$pid]['ref_commission'] += $commission;
                    }
                }
            }
        }

        if ((int)$a['include_zero'] === 1) {
            $prod_args=['post_type'=>'product','author'=>$vendor_id,'post_status'=>($a['product_status']==='any')?'any':array_map('trim', explode(',',$a['product_status'])),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true];
            $pids = get_posts($prod_args);
            foreach ($pids as $pid){
                if (!isset($rows[$pid])) {
                    $rows[$pid]=[
                        'name'=>get_the_title($pid),
                        'qty'=>0,
                        'sum'=>0.0,
                        'view'=>get_permalink($pid),
                        'thumb'=>$get_thumb($pid),
                        'author_id'=>$vendor_id,
                        'ref_qty'=>0,
                        'ref_sum'=>0.0,
                        'ref_commission'=>0.0,
                        'event_ts'=>$get_event_ts($pid),
                    ];
                }
            }
        }

        uasort($rows, static function(array $a, array $b): int {
            $dateDiff = ($b['event_ts'] ?? 0) <=> ($a['event_ts'] ?? 0);
            if ($dateDiff !== 0) {
                return $dateDiff;
            }
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        ob_start(); ?>
        <div class="tapin-sales-simple" dir="rtl" style="text-align:right">
          <h3>דוח מכירות</h3>
          <table class="widefat striped">
            <thead>
              <tr>
                <th style="width:68px">תמונה</th>
                <th>שם</th>
                <th>כמות</th>
                <th>סכום</th>
                <th>נמכר דרך הלינק</th>
                <th>עמלת לינק (₪)</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $pid => $r): $link_html = ($current_user_id === (int)($r['author_id'] ?? 0)) ? AffiliateLinkUI::renderForProduct((int) $pid) : ''; ?>
              <tr>
                <td><a href="<?php echo esc_url($r['view']); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($r['thumb']); ?>" alt="<?php echo esc_attr($r['name']); ?>" loading="lazy" style="width:56px;height:56px;object-fit:cover;border-radius:6px"></a></td>
                <td>
                  <a href="<?php echo esc_url($r['view']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['name']); ?></a>
                  <?php if (!empty($link_html)): ?>
                    <div style="margin-top:6px"><?php echo $link_html; ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format_i18n((int)$r['qty']); ?></td>
                <td><?php echo function_exists('wc_price') ? wc_price((float)$r['sum']) : esc_html(number_format_i18n((float)$r['sum'],2)); ?></td>
                <td>
                  <div>כמות: <?php echo number_format_i18n((int)($r['ref_qty'] ?? 0)); ?></div>
                  <div>סכום: <?php echo function_exists('wc_price') ? wc_price((float)($r['ref_sum'] ?? 0)) : esc_html(number_format_i18n((float)($r['ref_sum'] ?? 0),2)); ?></div>
                </td>
                <td><?php echo function_exists('wc_price') ? wc_price((float)($r['ref_commission'] ?? 0)) : esc_html(number_format_i18n((float)($r['ref_commission'] ?? 0),2)); ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6">אין נתונים זמינים.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>

        </div>
        <?php
        return ob_get_clean();
    }
}
