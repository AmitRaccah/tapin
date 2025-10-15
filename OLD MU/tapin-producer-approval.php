<?php
if (defined('WP_INSTALLING') && WP_INSTALLING) return;

function tapin_clean_meta_value($v){
    if (is_array($v)) $v = implode(', ', array_filter(array_map('strval', $v)));
    return trim(wp_strip_all_tags((string)$v));
}
function tapin_collect_producer_ids_from_order( WC_Order $order ) : array {
    $ids = [];
    foreach ( $order->get_items('line_item') as $item ) {
        $pid = $item->get_product_id();
        if (!$pid) continue;
        $author = (int) get_post_field('post_author', $pid);
        if ($author) $ids[] = $author;
    }
    return array_values(array_unique($ids));
}
function tapin_user_meta_multi($user_id, $keys){
    foreach ((array)$keys as $k){
        $raw = get_user_meta($user_id, $k, true);
        $v = tapin_clean_meta_value($raw);
        if ($v !== '') return $v;
    }
    return '';
}
function tapin_um_customer_profile($user_id){
    return [
        'first_name' => tapin_user_meta_multi($user_id, ['first_name','um_first_name']),
        'last_name'  => tapin_user_meta_multi($user_id, ['last_name','um_last_name']),
        'birthdate'  => tapin_user_meta_multi($user_id, ['birth_date','date_of_birth','um_birthdate','birthdate']),
        'gender'     => tapin_user_meta_multi($user_id, ['gender','um_gender','sex']),
        'facebook'   => tapin_user_meta_multi($user_id, ['facebook','facebook_url']),
        'instagram'  => tapin_user_meta_multi($user_id, ['instagram','instagram_url']),
        'whatsapp'   => tapin_user_meta_multi($user_id, ['whatsapp','whatsapp_number','whatsapp_phone','phone_whatsapp']),
    ];
}

add_action('woocommerce_checkout_order_processed', function($order_id){
    $order = wc_get_order($order_id);
    if (!$order) return;
    $producers = tapin_collect_producer_ids_from_order($order);
    if (!$producers) return;
    $order->update_meta_data('_tapin_producer_ids', $producers);
    if ('awaiting-producer' !== $order->get_status()) {
        $order->set_status('awaiting-producer', 'הוזז לסטטוס ממתין לאישור מפיק.');
    }
    $order->save();
}, 9999);

add_filter('woocommerce_payment_complete_order_status', function($status, $order_id, $order){
    if ($order && $order->get_meta('_tapin_producer_ids')) return 'awaiting-producer';
    return $status;
}, 10, 3);
add_filter('woocommerce_cod_process_payment_order_status', function($status, $order){
    if ($order && $order->get_meta('_tapin_producer_ids')) return 'awaiting-producer';
    return $status;
}, 10, 2);

add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order){
    static $guard = false;
    if ($guard || !$order) return;
    if (!$order->get_meta('_tapin_producer_ids')) return;
    if ($order->get_meta('_tapin_producer_approved')) return;
    if ($to !== 'awaiting-producer') {
        $guard = true;
        $order->update_status('awaiting-producer', 'Gate: reverted to awaiting-producer until producer approves.');
        $guard = false;
    }
}, 5, 4);

add_filter('woocommerce_payment_complete_reduce_order_stock', function($reduce, $order_id){
    $o = wc_get_order($order_id);
    if ($o && 'awaiting-producer' === $o->get_status()) return false;
    return $reduce;
}, 10, 2);
add_filter('woocommerce_email_enabled_customer_processing_order', function($enabled, $order){
    if ($order && $order->has_status('awaiting-producer')) return false;
    return $enabled;
}, 10, 2);

add_action('woocommerce_thankyou', function($order_id){
    $o = wc_get_order($order_id);
    if ($o && $o->has_status('awaiting-producer')) {
        echo '<p class="woocommerce-info" style="direction:rtl;text-align:right">ההזמנה התקבלה וממתינה לאישור המפיק. לא בוצעה גבייה/סליקה.</p>';
    }
});

function tapin_capture_and_mark_approved( WC_Order $order ) : bool {
    $did_capture = false;
    $pm = $order->get_payment_method();
    if ($pm && false !== strpos($pm, 'wcpay') && has_action('woocommerce_order_action_wcpay_capture_charge')) {
        do_action('woocommerce_order_action_wcpay_capture_charge', $order);
        $did_capture = true;
    }
    if (!$did_capture && $pm && false !== strpos($pm, 'stripe') && has_action('woocommerce_order_action_stripe_capture_charge')) {
        do_action('woocommerce_order_action_stripe_capture_charge', $order);
        $did_capture = true;
    }
    if (!$did_capture) {
        $order->add_order_note('אושר ע״י מפיק. אם נדרש – בצעו Capture ידני ב־Order actions.');
    }
    $order->update_meta_data('_tapin_producer_approved', 1);
    $all_virtual = true;
    foreach ($order->get_items('line_item') as $item) {
        $p = $item->get_product();
        if (!$p || !$p->is_virtual()) { $all_virtual = false; break; }
    }
    $order->update_status($all_virtual ? 'completed' : 'processing', 'אושר על ידי המפיק.');
    $order->save();
    return $did_capture;
}

add_shortcode('producer_order_approvals', function(){
    if (!is_user_logged_in()) return '<div class="woocommerce-info" style="direction:rtl;text-align:right">יש להתחבר למערכת.</div>';
    $me = wp_get_current_user();
    if (!array_intersect((array)$me->roles, ['producer','owner'])) return '<div class="woocommerce-error" style="direction:rtl;text-align:right">הדף למפיקים בלבד.</div>';
    $producer_id = get_current_user_id();

    $awaiting_ids = wc_get_orders([
        'status' => ['wc-awaiting-producer'],
        'limit'  => 200,
        'return' => 'ids',
    ]);
    foreach ($awaiting_ids as $oid) {
        $o = wc_get_order($oid);
        if ($o && !$o->get_meta('_tapin_producer_ids')) {
            $o->update_meta_data('_tapin_producer_ids', tapin_collect_producer_ids_from_order($o));
            $o->save();
        }
    }

    $order_ids = wc_get_orders([
        'status' => ['wc-awaiting-producer'],
        'limit'  => 200,
        'return' => 'ids',
    ]);

    $mine_ids = [];
    foreach ($order_ids as $oid) {
        $o = wc_get_order($oid);
        if (!$o) continue;
        $meta_ids = (array)$o->get_meta('_tapin_producer_ids');
        if ($meta_ids && in_array($producer_id, $meta_ids, true)) { $mine_ids[] = $oid; continue; }
        foreach ($o->get_items('line_item') as $it) {
            $pid = $it->get_product_id();
            if ($pid && (int)get_post_field('post_author', $pid) === $producer_id) { $mine_ids[] = $oid; break; }
        }
    }

    $notice = '';
    if ('POST' === $_SERVER['REQUEST_METHOD'] && !empty($_POST['tapin_pa_bulk_nonce']) && wp_verify_nonce($_POST['tapin_pa_bulk_nonce'],'tapin_pa_bulk')) {
        $approve_all = !empty($_POST['approve_all']);
        $cancel_sel  = isset($_POST['bulk_cancel']);
        $selected    = array_map('absint', (array)($_POST['order_ids'] ?? []));
        if ($approve_all) $selected = $mine_ids;
        $ok=0; $fail=0;
        foreach (array_unique($selected) as $oid) {
            if (!in_array($oid, $mine_ids, true)) { $fail++; continue; }
            $o = wc_get_order($oid);
            if (!$o || 'awaiting-producer' !== $o->get_status()) { $fail++; continue; }
            if ($cancel_sel) { $o->update_status('cancelled', 'נדחה/בוטל ע"י המפיק.'); $ok++; continue; }
            tapin_capture_and_mark_approved($o); $ok++;
        }
        if ($ok || $fail) $notice = '<div class="woocommerce-message" style="direction:rtl;text-align:right">טופלו '.$ok.' הזמנות, נכשלו '.$fail.'.</div>';
        $mine_ids = array_values(array_diff($mine_ids, $selected));
    }

    ob_start(); ?>
    <style>
      .tapin-pa{direction:rtl;text-align:right}
      .tapin-pa .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
      .tapin-pa .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:600}
      .tapin-pa .btn-primary{background:#16a34a;color:#fff}
      .tapin-pa .btn-danger{background:#ef4444;color:#fff}
      .tapin-pa .btn-ghost{background:#f1f5f9;color:#111827}
      .tapin-pa table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(2,6,23,.05)}
      .tapin-pa thead th{background:#f8fafc;font-weight:700;border-bottom:1px solid #e5e7eb;padding:10px;font-size:.95rem;white-space:nowrap;cursor:pointer}
      .tapin-pa tbody td{border-bottom:1px solid #f1f5f9;padding:10px;vertical-align:top}
      .tapin-pa tbody tr:last-child td{border-bottom:0}
      .tapin-pa .muted{color:#64748b;font-size:.92rem}
      .tapin-pa .wrap-links a{margin-inline-end:8px}
      .tapin-pa .select-col{width:36px;text-align:center}
    </style>

    <div class="tapin-pa">
      <?php echo $notice; ?>
      <h3>הזמנות הממתינות לאישור</h3>

      <form method="post" id="tapinBulkForm">
        <?php wp_nonce_field('tapin_pa_bulk','tapin_pa_bulk_nonce'); ?>

        <div class="toolbar">
          <button class="btn btn-primary" type="submit" name="bulk_approve">אשר נבחרים</button>
          <button class="btn btn-ghost"   type="button" id="tapinApproveAll">אשר הכל</button>
          <button class="btn btn-danger"  type="submit" name="bulk_cancel" onclick="return confirm('לבטל את ההזמנות הנבחרות?')">בטל נבחרים</button>
        </div>

        <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">

        <table id="tapinOrdersTable">
          <thead>
            <tr>
              <th class="select-col"><input type="checkbox" id="tapinSelectAll" aria-label="בחר הכל"></th>
              <th data-sort>מס׳ הזמנה</th>
              <th data-sort>שם פרטי</th>
              <th data-sort>שם משפחה</th>
              <th data-sort>תאריך לידה</th>
              <th data-sort>מין</th>
              <th data-sort>Facebook</th>
              <th data-sort>Instagram</th>
              <th data-sort>WhatsApp</th>
              <th data-sort>סכום</th>
              <th>פריטים</th>
              <th data-sort>תאריך הזמנה</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($mine_ids):
                foreach ($mine_ids as $oid):
                    $order = wc_get_order($oid);
                    $uid   = (int)$order->get_user_id();
                    $um    = $uid ? tapin_um_customer_profile($uid) : [
                        'first_name' => $order->get_billing_first_name(),
                        'last_name'  => $order->get_billing_last_name(),
                        'birthdate'  => '',
                        'gender'     => '',
                        'facebook'   => '',
                        'instagram'  => '',
                        'whatsapp'   => '',
                    ];
                    $items_txt = [];
                    foreach ($order->get_items('line_item') as $it){
                        $items_txt[] = esc_html($it->get_name()).' × '.(int)$it->get_quantity();
                    }
                    $created = $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format').' H:i') : '';
                    $total   = wp_strip_all_tags($order->get_formatted_order_total());
            ?>
            <tr>
              <td class="select-col"><input type="checkbox" name="order_ids[]" value="<?php echo (int)$oid; ?>"></td>
              <td>#<?php echo esc_html($order->get_order_number()); ?></td>
              <td><?php echo esc_html($um['first_name']); ?></td>
              <td><?php echo esc_html($um['last_name']); ?></td>
              <td><?php echo esc_html($um['birthdate']); ?></td>
              <td><?php echo esc_html($um['gender']); ?></td>
              <td class="wrap-links"><?php echo $um['facebook'] ? '<a href="'.esc_url($um['facebook']).'" target="_blank" rel="noopener">פרופיל</a>' : '<span class="muted">—</span>'; ?></td>
              <td class="wrap-links"><?php echo $um['instagram'] ? '<a href="'.esc_url($um['instagram']).'" target="_blank" rel="noopener">@ אינסטגרם</a>' : '<span class="muted">—</span>'; ?></td>
              <td>
                <?php if ($um['whatsapp']): ?>
                  <a href="https://wa.me/<?php echo esc_attr(preg_replace('/\D+/','',$um['whatsapp'])); ?>" target="_blank" rel="noopener"><?php echo esc_html($um['whatsapp']); ?></a>
                <?php else: ?><span class="muted">—</span><?php endif; ?>
              </td>
              <td><?php echo esc_html($total); ?></td>
              <td class="muted"><?php echo implode('<br>', array_map('wp_kses_post',$items_txt)); ?></td>
              <td><?php echo esc_html($created); ?></td>
            </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="12" class="muted" style="text-align:center">אין כרגע הזמנות ממתינות.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </form>
    </div>

    <script>
    (function(){
      var form=document.getElementById('tapinBulkForm');
      var selAll=document.getElementById('tapinSelectAll');
      if(selAll&&form){
        selAll.addEventListener('change',function(){
          form.querySelectorAll('tbody input[type="checkbox"][name="order_ids[]"]').forEach(function(cb){ cb.checked=selAll.checked; });
        });
      }
      var btnAll=document.getElementById('tapinApproveAll');
      var fldAll=document.getElementById('tapinApproveAllField');
      if(btnAll&&fldAll){
        btnAll.addEventListener('click',function(){
          fldAll.value='1';
          var btn=document.createElement('input');
          btn.type='hidden'; btn.name='bulk_approve'; btn.value='1';
          form.appendChild(btn);
          form.submit();
        });
      }
      var table=document.getElementById('tapinOrdersTable');
      if(table){
        var ths=table.querySelectorAll('thead th[data-sort]');
        ths.forEach(function(th){
          th.addEventListener('click',function(){
            var tbody=table.tBodies[0];
            var rows=Array.from(tbody.querySelectorAll('tr'));
            var col=Array.from(th.parentNode.children).indexOf(th);
            var asc=th.dataset.dir!=='asc';
            rows.sort(function(a,b){
              var ta=(a.children[col].textContent||'').trim().toLowerCase();
              var tb=(b.children[col].textContent||'').trim().toLowerCase();
              if(ta<tb) return asc?-1:1;
              if(ta>tb) return asc?1:-1;
              return 0;
            });
            tbody.innerHTML='';
            rows.forEach(function(r){ tbody.appendChild(r); });
            ths.forEach(function(x){ delete x.dataset.dir; });
            th.dataset.dir=asc?'asc':'desc';
          });
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
});
