<?php
if (defined('WP_INSTALLING') && WP_INSTALLING) return;

add_shortcode('producer_event_sales', function($atts){
    if (!function_exists('wc_get_orders')) return '<p>WooCommerce נדרש.</p>';

    $a = shortcode_atts([
        'vendor'         => 'current',
        'from'           => '',
        'to'             => '',
        'statuses'       => 'processing,completed',
        'include_zero'   => '1',
        'product_status' => 'publish'
    ], $atts, 'producer_event_sales');

    if (!is_user_logged_in()) {
        status_header(403);
        return '<div style="direction:rtl;text-align:right;background:#fff4f4;border:1px solid #f3c2c2;padding:12px;border-radius:8px">הדף זמין למשתמשים מורשים בלבד. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחבר/י</a>.</div>';
    }
    $me         = wp_get_current_user();
    $is_admin   = current_user_can('manage_woocommerce');
    $role_ok    = array_intersect((array)$me->roles, ['producer','owner']);
    if (!$is_admin && empty($role_ok)) {
        status_header(403);
        return '<div style="direction:rtl;text-align:right;background:#fff4f4;border:1px solid #f3c2c2;padding:12px;border-radius:8px">אין לך הרשאה לצפות בדף זה. הדף מיועד למפיקים או לבעלי האתר.</div>';
    }
    $can_view_all = $is_admin || in_array('owner', (array)$me->roles, true);

    $vendor_id = 0;
    if ($a['vendor'] === 'current') {
        $vendor_id = get_current_user_id();
    } elseif (ctype_digit((string)$a['vendor'])) {
        $vendor_id = (int)$a['vendor'];
    } else {
        $u = get_user_by('slug', sanitize_title($a['vendor']));
        if (!$u) $u = get_user_by('login', sanitize_user($a['vendor']));
        if ($u) $vendor_id = (int)$u->ID;
    }
    if (!$vendor_id) return '<p>לא נמצא מפיק.</p>';
    if (!$can_view_all && get_current_user_id() !== $vendor_id) {
        status_header(403);
        return '<p style="direction:rtl;text-align:right">אין לך הרשאה לצפות בדוח של משתמש אחר.</p>';
    }

    $get_thumb = function($pid){
        $url = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
        if (!$url && function_exists('wc_placeholder_img_src')) $url = wc_placeholder_img_src();
        if (!$url) $url = includes_url('images/media/default.png');
        return $url;
    };

    $date_after  = $a['from'] ? date_i18n('Y-m-d 00:00:00', strtotime(sanitize_text_field($a['from']))) : '';
    $date_before = $a['to']   ? date_i18n('Y-m-d 23:59:59', strtotime(sanitize_text_field($a['to'])))   : '';
    $statuses    = array_filter(array_map(function($s){ return 'wc-'.sanitize_key(trim($s)); }, explode(',', $a['statuses'])));

    $args1 = [
        'limit'      => -1,
        'type'       => 'shop_order',
        'status'     => $statuses ?: ['wc-processing','wc-completed'],
        'meta_key'   => '_dokan_vendor_id',
        'meta_value' => $vendor_id,
        'return'     => 'ids',
    ];
    if ($date_after || $date_before) {
        $args1['date_created'] = array_filter([
            'after'     => $date_after ?: null,
            'before'    => $date_before ?: null,
            'inclusive' => true,
        ]);
    }
    $order_ids = wc_get_orders($args1);

    $rows = [];

    $accumulate = function($order_ids) use (&$rows, $get_thumb) {
        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            foreach ($order->get_items('line_item') as $item) {
                $pid = (int)$item->get_product_id();
                if (!$pid) continue;
                if (!isset($rows[$pid])) {
                    $rows[$pid] = [
                        'name'  => get_the_title($pid),
                        'qty'   => 0,
                        'sum'   => 0.0,
                        'view'  => get_permalink($pid),
                        'thumb' => $get_thumb($pid),
                    ];
                }
                $rows[$pid]['qty']  += (int)$item->get_quantity();
                $rows[$pid]['sum']  += (float)$item->get_total();
            }
        }
    };

    if (!empty($order_ids)) {
        $accumulate($order_ids);
    } else {
        $args2 = [
            'limit'  => -1,
            'type'   => 'shop_order',
            'status' => $statuses ?: ['wc-processing','wc-completed'],
            'return' => 'ids',
        ];
        if ($date_after || $date_before) {
            $args2['date_created'] = array_filter([
                'after'     => $date_after ?: null,
                'before'    => $date_before ?: null,
                'inclusive' => true,
            ]);
        }
        $porder_ids = wc_get_orders($args2);
        foreach ($porder_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            foreach ($order->get_items('line_item') as $item) {
                $pid = (int)$item->get_product_id();
                if (!$pid) continue;
                $author = (int)get_post_field('post_author', $pid);
                if ($author !== $vendor_id) continue;
                if (!isset($rows[$pid])) {
                    $rows[$pid] = [
                        'name'  => get_the_title($pid),
                        'qty'   => 0,
                        'sum'   => 0.0,
                        'view'  => get_permalink($pid),
                        'thumb' => $get_thumb($pid),
                    ];
                }
                $rows[$pid]['qty'] += (int)$item->get_quantity();
                $rows[$pid]['sum'] += (float)$item->get_total();
            }
        }
    }

    if ((int)$a['include_zero'] === 1) {
        $prod_args = [
            'post_type'      => 'product',
            'author'         => $vendor_id,
            'post_status'    => ($a['product_status'] === 'any') ? 'any' : array_map('trim', explode(',', $a['product_status'])),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        $pids = get_posts($prod_args);
        foreach ($pids as $pid) {
            if (!isset($rows[$pid])) {
                $rows[$pid] = [
                    'name'  => get_the_title($pid),
                    'qty'   => 0,
                    'sum'   => 0.0,
                    'view'  => get_permalink($pid),
                    'thumb' => $get_thumb($pid),
                ];
            }
        }
    }

    uasort($rows, function($a,$b){ return strcasecmp($a['name'],$b['name']); });

    ob_start(); ?>
    <div class="tapin-sales-simple" dir="rtl" style="text-align:right">
      <h3>דוח מכירות</h3>
      <table class="widefat striped">
        <thead>
          <tr>
            <th style="width:68px">תמונה</th>
            <th>אירוע</th>
            <th>נמכרו</th>
            <th>סכום</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="<?php echo esc_url($r['view']); ?>" target="_blank" rel="noopener">
                <img src="<?php echo esc_url($r['thumb']); ?>" alt="<?php echo esc_attr($r['name']); ?>" loading="lazy" style="width:56px;height:56px;object-fit:cover;border-radius:6px;display:block">
              </a>
            </td>
            <td><a href="<?php echo esc_url($r['view']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['name']); ?></a></td>
            <td><?php echo number_format_i18n((int)$r['qty']); ?></td>
            <td><?php echo wc_price((float)$r['sum']); ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4">אין נתונים לתצוגה.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
});
