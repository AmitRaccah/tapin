<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Features\Orders\ProducerApprovals\Assets as ProducerApprovalsAssets;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\UI\Components\AffiliateLinkUI;
use WC_Order_Item_Product;

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
            $order_ts = 0;
            $created = $order->get_date_created();
            if ($created instanceof \WC_DateTime) {
                $order_ts = $created->getTimestamp();
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
                    $rows[$pid] = $this->createEventRow($pid, $get_thumb, $get_event_ts, $author, $get_commission_meta($pid));
                } elseif (empty($rows[$pid]['commission_meta'])) {
                    $rows[$pid]['commission_meta'] = $get_commission_meta($pid);
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

                $this->accumulateTicketStats($rows[$pid], $item, $was_referred, $order_ts);
            }
        }

        if ((int)$a['include_zero'] === 1) {
            $prod_args=['post_type'=>'product','author'=>$vendor_id,'post_status'=>($a['product_status']==='any')?'any':array_map('trim', explode(',',$a['product_status'])),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true];
            $pids = get_posts($prod_args);
            foreach ($pids as $pid){
                if (!isset($rows[$pid])) {
                    $author = $get_author((int) $pid) ?: $vendor_id;
                    $rows[$pid] = $this->createEventRow((int) $pid, $get_thumb, $get_event_ts, (int) $author, $get_commission_meta((int) $pid));
                } elseif (empty($rows[$pid]['commission_meta'])) {
                    $rows[$pid]['commission_meta'] = $get_commission_meta((int) $pid);
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
         <div class="tapin-pa tapin-sales" dir="rtl">
          <h3><?php echo esc_html(self::TEXT['page_heading']); ?></h3>
          <?php if ($rows): ?>
            <div class="tapin-pa__events" id="tapinPaEvents">
              <?php $loopIndex = 0; ?>
              <?php foreach ($rows as $pid => $r):
                  $isOpen = $loopIndex === 0;
                  $loopIndex++;
                  $stats = isset($r['stats']) && is_array($r['stats']) ? $r['stats'] : [];
                  $regularTotal = (int) ($stats['regular_total'] ?? 0);
                  $regularLink = (int) ($stats['regular_affiliate'] ?? 0);
                  $regularDirect = (int) ($stats['regular_direct'] ?? max(0, $regularTotal - $regularLink));
                  $specialTypes = isset($stats['special_types']) && is_array($stats['special_types']) ? $stats['special_types'] : [];
                  if ($specialTypes) {
                      uasort($specialTypes, static function (array $a, array $b): int {
                          return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
                      });
                  }
                  $windowStats = isset($stats['windows']) && is_array($stats['windows']) ? $stats['windows'] : [];
                  $link_html = ($current_user_id === (int) ($r['author_id'] ?? 0)) ? AffiliateLinkUI::renderForProduct((int) $pid) : '';
                  $search_blob = $this->buildSearchBlob($r);
                  $affCommission = (float) ($r['ref_commission'] ?? 0);
                  $eventDateLabel = isset($r['event_date_label']) ? (string) $r['event_date_label'] : '';
                  $sumTotal = (float) ($r['sum'] ?? 0.0);
                  $sumAffiliate = (float) ($r['ref_sum'] ?? 0.0);
                  $sumDirect = max(0.0, $sumTotal - $sumAffiliate);
                  $producerCommissionLabel = $this->describeProducerCommission($r['commission_meta'] ?? []);
              ?>
                <div class="tapin-pa-event<?php echo $isOpen ? ' is-open' : ''; ?>" data-search="<?php echo esc_attr($search_blob); ?>">
                  <button class="tapin-pa-event__header" type="button" data-event-toggle aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                    <div class="tapin-pa-event__summary">
                      <?php if (!empty($r['thumb'])): ?>
                        <img class="tapin-pa-event__image" src="<?php echo esc_url($r['thumb']); ?>" alt="<?php echo esc_attr($r['name']); ?>" loading="lazy">
                      <?php else: ?>
                        <div class="tapin-pa-event__image" aria-hidden="true"></div>
                      <?php endif; ?>
                      <div class="tapin-pa-event__text">
                        <h4>
                          <?php if (!empty($r['view'])): ?>
                            <a href="<?php echo esc_url($r['view']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['name']); ?></a>
                          <?php else: ?>
                            <?php echo esc_html($r['name']); ?>
                          <?php endif; ?>
                        </h4>
                        <?php if ($eventDateLabel !== ''): ?>
                          <div style="font-size:.85rem;color:#475569;margin-top:2px;"><?php echo esc_html($eventDateLabel); ?></div>
                        <?php endif; ?>
                        <div class="tapin-pa-event__stats">
                          <span class="tapin-pa-event__badge"><?php echo esc_html(self::TEXT['total_tickets']); ?>: <?php echo number_format_i18n((int) ($r['qty'] ?? 0)); ?></span>
                          <span class="tapin-pa-event__badge"><?php echo esc_html(self::TEXT['total_revenue']); ?>: <?php echo function_exists('wc_price') ? wc_price((float) ($r['sum'] ?? 0)) : esc_html(number_format_i18n((float) ($r['sum'] ?? 0), 2)); ?></span>
                          <span class="tapin-pa-event__badge"><?php echo esc_html(self::TEXT['regular_link']); ?>: <?php echo number_format_i18n($regularLink); ?></span>
                        </div>
                      </div>
                    </div>
                    <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
                  </button>
                  <div class="tapin-pa-event__panel<?php echo $isOpen ? '' : ' hidden'; ?>">
                    <?php if (!empty($link_html)): ?>
                      <div class="tapin-pa-event__actions">
                        <?php echo $link_html; ?>
                      </div>
                    <?php endif; ?>
                    <article class="tapin-pa-order tapin-pa-order--alt" data-search="<?php echo esc_attr($search_blob); ?>">
                      <header class="tapin-pa-order__header">
                        <div class="tapin-pa-order__meta">
                          <span><?php echo esc_html(self::TEXT['regular_link']); ?>: <?php echo number_format_i18n($regularLink); ?></span>
                          <span><?php echo esc_html(self::TEXT['regular_not_link']); ?>: <?php echo number_format_i18n($regularDirect); ?></span>
                          <span><?php echo esc_html(self::TEXT['affiliate_fee']); ?>: <?php echo function_exists('wc_price') ? wc_price($affCommission) : esc_html(number_format_i18n($affCommission, 2)); ?></span>
                        </div>
                      </header>
                      <div class="tapin-pa-order__body">
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html(self::TEXT['regular_heading']); ?></h5>
                          <div class="tapin-pa-order__grid">
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['regular_total']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularTotal); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['from_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularLink); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['not_from_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo number_format_i18n($regularDirect); ?></div>
                            </div>
                          </div>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html(self::TEXT['amounts_heading']); ?></h5>
                          <div class="tapin-pa-order__grid">
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['sum_total']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumTotal); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['sum_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumAffiliate); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['sum_direct']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($sumDirect); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['sum_commission_link']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo $this->formatMoney($affCommission); ?></div>
                            </div>
                            <div class="tapin-pa-order__card">
                              <div class="tapin-pa-order__label"><?php echo esc_html(self::TEXT['producer_commission']); ?></div>
                              <div class="tapin-pa-order__value"><?php echo esc_html($producerCommissionLabel); ?></div>
                            </div>
                          </div>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html(self::TEXT['special_heading']); ?></h5>
                          <?php if ($specialTypes): ?>
                            <ul class="tapin-pa-order__lines">
                              <?php foreach ($specialTypes as $special): ?>
                                <?php
                                $label = isset($special['label']) ? (string) $special['label'] : '';
                                $qty = isset($special['qty']) ? (int) $special['qty'] : 0;
                                ?>
                                <li><?php echo esc_html($label !== '' ? $label : self::TEXT['special_heading']); ?> &mdash; <?php echo number_format_i18n($qty); ?></li>
                              <?php endforeach; ?>
                            </ul>
                          <?php else: ?>
                            <div class="tapin-pa__warning"><?php echo esc_html(self::TEXT['special_empty']); ?></div>
                          <?php endif; ?>
                        </div>
                        <div class="tapin-pa-order__section">
                          <h5 class="tapin-pa-order__section-title"><?php echo esc_html(self::TEXT['windows_heading']); ?></h5>
                          <?php if ($windowStats): ?>
                            <div class="tapin-pa-order__grid">
                              <?php foreach ($windowStats as $windowIndex => $window): ?>
                                <?php
                                $windowLabel = isset($window['label']) && $window['label'] !== ''
                                    ? (string) $window['label']
                                    : sprintf('%s #%d', self::TEXT['window_single'], $windowIndex + 1);
                                $windowLink = (int) ($window['affiliate'] ?? 0);
                                $windowDirect = (int) ($window['direct'] ?? 0);
                                ?>
                                <div class="tapin-pa-order__card">
                                  <div class="tapin-pa-order__label"><?php echo esc_html($windowLabel); ?></div>
                                  <div class="tapin-pa-order__value">
                                    <div><?php echo esc_html(self::TEXT['from_link']); ?>: <?php echo number_format_i18n($windowLink); ?></div>
                                    <div><?php echo esc_html(self::TEXT['not_from_link']); ?>: <?php echo number_format_i18n($windowDirect); ?></div>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php else: ?>
                            <div class="tapin-pa__warning"><?php echo esc_html(self::TEXT['windows_empty']); ?></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </article>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="tapin-pa-empty"><?php echo esc_html(self::TEXT['empty_state']); ?></div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    private function createEventRow(int $productId, callable $getThumb, callable $getEventTs, int $authorId, array $commissionMeta = []): array {
        $ticketTypes = TicketTypesRepository::get($productId);
        $ticketIndex = $this->indexTicketTypes($ticketTypes);
        $regular = $this->resolveRegularTicket($ticketTypes);
        $eventTs = $getEventTs($productId);

        return [
            'name'              => get_the_title($productId),
            'qty'               => 0,
            'sum'               => 0.0,
            'view'              => get_permalink($productId),
            'thumb'             => $getThumb($productId),
            'author_id'         => $authorId,
            'ref_qty'           => 0,
            'ref_sum'           => 0.0,
            'ref_commission'    => 0.0,
            'event_ts'          => $eventTs,
            'event_date_label'  => $this->formatEventDate($eventTs),
            'ticket_index'      => $ticketIndex,
            'regular_type_id'   => $regular['id'],
            'regular_type_label'=> $regular['label'],
            'commission_meta'   => $commissionMeta,
            'stats'             => [
                'regular_total'     => 0,
                'regular_affiliate' => 0,
                'regular_direct'    => 0,
                'special_types'     => [],
                'windows'           => $this->buildWindowBuckets($productId, $ticketTypes),
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    private function indexTicketTypes(array $ticketTypes): array {
        $index = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type)) {
                continue;
            }
            $id = isset($type['id']) ? (string) $type['id'] : '';
            if ($id === '') {
                continue;
            }
            $index[$id] = [
                'name' => isset($type['name']) ? (string) $type['name'] : $id,
            ];
        }
        return $index;
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array{id:string,label:string}
     */
    private function resolveRegularTicket(array $ticketTypes): array {
        foreach ($ticketTypes as $type) {
            if (isset($type['id']) && (string) $type['id'] === 'general') {
                return [
                    'id'    => 'general',
                    'label' => isset($type['name']) ? (string) $type['name'] : 'general',
                ];
            }
        }

        if ($ticketTypes !== []) {
            $first = $ticketTypes[0];
            return [
                'id'    => isset($first['id']) ? (string) $first['id'] : '',
                'label' => isset($first['name']) ? (string) $first['name'] : '',
            ];
        }

        return ['id' => '', 'label' => ''];
    }

    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    private function buildWindowBuckets(int $productId, array $ticketTypes): array {
        $windows = SaleWindowsRepository::get($productId, $ticketTypes);
        $buckets = [];
        foreach ($windows as $index => $window) {
            $start = (int) ($window['start'] ?? 0);
            $end   = (int) ($window['end'] ?? 0);
            $buckets[] = [
                'start'     => $start,
                'end'       => $end,
                'label'     => $this->formatWindowLabel($start, $end, (int) $index + 1),
                'affiliate' => 0,
                'direct'    => 0,
            ];
        }
        return $buckets;
    }

    private function formatWindowLabel(int $start, int $end, int $position): string {
        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        $format = get_option('date_format') . ' H:i';
        $from = $start > 0 ? wp_date($format, $start, $tz) : '';
        $to   = $end > 0 ? wp_date($format, $end, $tz) : '';

        if ($from && $to) {
            return sprintf('%s %s %s %s', self::TEXT['range_from'], $from, self::TEXT['range_to'], $to);
        }
        if ($from) {
            return sprintf('%s %s', self::TEXT['range_from'], $from);
        }
        if ($to) {
            return sprintf('%s %s', self::TEXT['range_to'], $to);
        }
        return sprintf('%s #%d', self::TEXT['window_single'], max(1, $position));
    }

    private function formatEventDate(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        $format = get_option('date_format') . ' H:i';
        return wp_date($format, $timestamp, function_exists('wp_timezone') ? wp_timezone() : null);
    }

    private function buildSearchBlob(array $row): string {
        $parts = [];
        if (!empty($row['name'])) {
            $parts[] = (string) $row['name'];
        }
        if (!empty($row['regular_type_label'])) {
            $parts[] = (string) $row['regular_type_label'];
        }
        $special = $row['stats']['special_types'] ?? [];
        if (is_array($special)) {
            foreach ($special as $entry) {
                if (!empty($entry['label'])) {
                    $parts[] = (string) $entry['label'];
                }
            }
        }
        $text = trim(implode(' ', $parts));
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($text === null) {
            $text = '';
        }
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }
        return trim($text);
    }

    private function accumulateTicketStats(array &$eventRow, WC_Order_Item_Product $item, bool $wasReferred, int $orderTs): void {
        $tickets = $this->extractTicketsFromItem($item);
        if ($tickets === []) {
            $count = max(1, (int) $item->get_quantity());
            $tickets = array_fill(0, $count, ['ticket_type' => '', 'ticket_type_label' => '']);
        }

        foreach ($tickets as $ticket) {
            $typeId   = isset($ticket['ticket_type']) ? (string) $ticket['ticket_type'] : '';
            $label    = isset($ticket['ticket_type_label']) ? (string) $ticket['ticket_type_label'] : '';
            $resolved = $this->resolveTicketTypeId($typeId, $label, $eventRow['ticket_index'] ?? []);

            if ($this->isRegularTicket($resolved, $label, (string) ($eventRow['regular_type_id'] ?? ''), (string) ($eventRow['regular_type_label'] ?? ''))) {
                $eventRow['stats']['regular_total'] = (int) ($eventRow['stats']['regular_total'] ?? 0) + 1;
                if ($wasReferred) {
                    $eventRow['stats']['regular_affiliate'] = (int) ($eventRow['stats']['regular_affiliate'] ?? 0) + 1;
                } else {
                    $eventRow['stats']['regular_direct'] = (int) ($eventRow['stats']['regular_direct'] ?? 0) + 1;
                }
                $this->incrementWindowBuckets($eventRow['stats']['windows'], $orderTs, $wasReferred);
                continue;
            }

            $key = $resolved !== '' ? 'id:' . $resolved : 'label:' . md5($label ?: wp_json_encode($ticket));
            if (!isset($eventRow['stats']['special_types'][$key])) {
                $fallbackLabel = '';
                if ($resolved !== '' && isset($eventRow['ticket_index'][$resolved]['name'])) {
                    $fallbackLabel = (string) $eventRow['ticket_index'][$resolved]['name'];
                }
                $eventRow['stats']['special_types'][$key] = [
                    'label' => $label !== '' ? $label : $fallbackLabel,
                    'qty'   => 0,
                ];
            }
            $eventRow['stats']['special_types'][$key]['qty'] = (int) ($eventRow['stats']['special_types'][$key]['qty'] ?? 0) + 1;
        }
    }

    private function resolveTicketTypeId(string $typeId, string $label, array $ticketIndex): string {
        if ($typeId !== '' && isset($ticketIndex[$typeId])) {
            return $typeId;
        }
        if ($label !== '') {
            foreach ($ticketIndex as $id => $meta) {
                $name = isset($meta['name']) ? (string) $meta['name'] : '';
                if ($name !== '' && AttendeeFields::labelsEqual($name, $label)) {
                    return (string) $id;
                }
            }
        }
        return $typeId;
    }

    private function isRegularTicket(string $typeId, string $label, string $regularTypeId, string $regularLabel): bool {
        if ($regularTypeId !== '' && $typeId === $regularTypeId) {
            return true;
        }
        if ($regularLabel !== '' && $label !== '' && AttendeeFields::labelsEqual($regularLabel, $label)) {
            return true;
        }
        if ($regularTypeId === '' && $regularLabel === '') {
            return true;
        }
        return false;
    }

    private function incrementWindowBuckets(array &$windows, int $orderTs, bool $wasReferred): void {
        if ($orderTs <= 0 || !is_array($windows)) {
            return;
        }
        foreach ($windows as &$window) {
            if (!is_array($window)) {
                continue;
            }
            $start = (int) ($window['start'] ?? 0);
            $end   = (int) ($window['end'] ?? 0);
            if ($this->timestampInWindow($orderTs, $start, $end)) {
                $key = $wasReferred ? 'affiliate' : 'direct';
                $window[$key] = (int) ($window[$key] ?? 0) + 1;
                break;
            }
        }
        unset($window);
    }

    private function timestampInWindow(int $ts, int $start, int $end): bool {
        if ($ts <= 0) {
            return false;
        }
        if ($start > 0 && $ts < $start) {
            return false;
        }
        if ($end > 0 && $ts > $end) {
            return false;
        }
        return true;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function extractTicketsFromItem(WC_Order_Item_Product $item): array {
        $decoded = AttendeeSecureStorage::decrypt((string) $item->get_meta('_tapin_attendees_json', true));
        if ($decoded !== []) {
            return array_map([$this, 'normalizeTicketMeta'], $decoded);
        }

        $legacy = (string) $item->get_meta('Tapin Attendees', true);
        if ($legacy !== '') {
            $legacyDecoded = AttendeeSecureStorage::decrypt($legacy);
            if ($legacyDecoded !== []) {
                return array_map([$this, 'normalizeTicketMeta'], $legacyDecoded);
            }
        }

        $order = $item->get_order();
        if ($order instanceof \WC_Order) {
            $aggregate = $order->get_meta('_tapin_attendees', true);
            $aggregateDecoded = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            if ($aggregateDecoded !== []) {
                return array_map([$this, 'normalizeTicketMeta'], $aggregateDecoded);
            }
        }

        $fallback = [];
        $summaryKeys = AttendeeFields::summaryKeys();
        foreach ($item->get_formatted_meta_data('') as $meta) {
            $label = (string) $meta->key;
            if (
                strpos($label, "\u{05D4}\u{05DE}\u{05E9}\u{05EA}\u{05EA}\u{05E3}") === 0 ||
                strpos($label, 'Participant') === 0
            ) {
                $parts = array_map('trim', explode('|', $meta->value));
                $data  = array_combine($summaryKeys, array_pad($parts, count($summaryKeys), ''));
                if ($data !== false) {
                    $fallback[] = $this->normalizeTicketMeta($data);
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string,string> $data
     */
    private function normalizeTicketMeta(array $data): array {
        $type = isset($data['ticket_type']) ? sanitize_key((string) $data['ticket_type']) : '';
        $label = isset($data['ticket_type_label']) ? trim(wp_strip_all_tags((string) $data['ticket_type_label'])) : '';

        return [
            'ticket_type'       => $type,
            'ticket_type_label' => $label,
        ];
    }

    private function formatMoney(float $value): string {
        if (function_exists('wc_price')) {
            return wc_price($value);
        }
        return esc_html(number_format_i18n($value, 2));
    }

    private function describeProducerCommission(array $meta): string {
        $type = isset($meta['type']) ? (string) $meta['type'] : '';
        $amount = isset($meta['amount']) ? (float) $meta['amount'] : 0.0;

        if ($type === 'percent' && $amount > 0) {
            return sprintf(
                '%s%% (%s)',
                number_format_i18n($amount, 2),
                self::TEXT['producer_commission_percent']
            );
        }

        if ($type === 'flat' && $amount > 0) {
            $money = function_exists('wc_price') ? wc_price($amount) : esc_html(number_format_i18n($amount, 2));
            return sprintf(
                '%s (%s)',
                wp_strip_all_tags($money),
                self::TEXT['producer_commission_flat']
            );
        }

        return self::TEXT['producer_commission_none'];
    }
}
