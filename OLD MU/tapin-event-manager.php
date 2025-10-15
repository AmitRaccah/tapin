<?php
if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/* =========================================================
 * Constants (single source of truth for meta keys)
 * ========================================================= */
if (!defined('TAPIN_META_SALE_WINDOWS')) define('TAPIN_META_SALE_WINDOWS', '_tapin_sale_windows');
if (!defined('TAPIN_META_EVENT_DATE'))   define('TAPIN_META_EVENT_DATE',   'event_date');
if (!defined('TAPIN_META_PAUSED'))       define('TAPIN_META_PAUSED',       '_sale_paused');
if (!defined('TAPIN_META_EDIT_REQ'))     define('TAPIN_META_EDIT_REQ',     'tapin_edit_request');

/* =========================================================
 * Shared CSS (vars + base)
 * ========================================================= */
function tapin_get_shared_css() {
    return '
    :root {
        --tapin-radius-md: 12px;
        --tapin-radius-lg: 16px;
        --tapin-primary-color: #2a1a5e;
        --tapin-text-dark: #1f2937;
        --tapin-text-light: #334155;
        --tapin-border-color: #e5e7eb;
        --tapin-success-bg: #16a34a;
        --tapin-danger-bg: #ef4444;
        --tapin-warning-bg: #f59e0b;
        --tapin-info-bg: #0ea5e9;
        --tapin-ghost-bg: #f1f5f9;
        --tapin-card-shadow: 0 4px 12px rgba(2,6,23,.05);
    }
    .tapin-center-container { max-width: 1100px; margin-inline: auto; direction: rtl; text-align: right; }
    .tapin-center-container *, .tapin-center-container *::before, .tapin-center-container *::after { box-sizing: border-box; }
    .tapin-title { font-size: 28px; font-weight: 800; color: var(--tapin-primary-color); margin: 14px 0 20px; }
    .tapin-form-grid { display: grid; gap: 16px; }
    .tapin-card {
        background: #fff;
        border: 1px solid var(--tapin-border-color);
        border-radius: var(--tapin-radius-lg);
        padding: 20px;
        box-shadow: var(--tapin-card-shadow);
        transition: opacity .3s;
    }
    .tapin-card--paused { border-left: 4px solid var(--tapin-warning-bg); opacity: 0.85; }
    .tapin-card__header { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 16px; }
    .tapin-card__thumb { width: 80px; height: 80px; object-fit: cover; border-radius: var(--tapin-radius-md); display: block; flex-shrink: 0; }
    .tapin-card__title { margin: 0 0 8px; font-size: 1.25rem; }
    .tapin-card__title a { color: inherit; text-decoration: none; }
    .tapin-card__title a:hover { color: var(--tapin-primary-color); }
    .tapin-card__meta { font-size: 0.9rem; color: var(--tapin-text-light); }
    .tapin-status-badge { font-size: 12px; font-weight: 700; vertical-align: middle; margin-right: 6px; }
    .tapin-status-badge--paused { color: var(--tapin-warning-bg); }
    .tapin-status-badge--pending { color: var(--tapin-info-bg); }
    .tapin-form-row { margin-bottom: 16px; }
    .tapin-form-row:last-child { margin-bottom: 0; }
    .tapin-form-row label { display: block; margin-bottom: 6px; font-weight: 700; color: var(--tapin-text-dark); }
    .tapin-form-row input[type="text"],
    .tapin-form-row input[type="number"],
    .tapin-form-row input[type="datetime-local"],
    .tapin-form-row textarea,
    .tapin-form-row input[type="file"] {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--tapin-border-color);
        border-radius: var(--tapin-radius-md);
        background: #fff;
        transition: border-color .2s, box-shadow .2s;
    }
    .tapin-form-row input:focus, .tapin-form-row textarea:focus {
        border-color: var(--tapin-primary-color);
        box-shadow: 0 0 0 3px rgba(42, 26, 94, 0.1);
        outline: none;
    }
    .tapin-columns-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .tapin-actions { display: flex; gap: 12px; align-items: center; margin-top: 20px; flex-wrap: wrap; }
    .tapin-btn { padding: 12px 20px; border-radius: var(--tapin-radius-md); border: 0; cursor: pointer; font-weight: 600; transition: opacity .2s; font-size: 1rem; }
    .tapin-btn:hover { opacity: 0.85; }
    .tapin-btn--primary { background: var(--tapin-success-bg); color: #fff; }
    .tapin-btn--danger  { background: var(--tapin-danger-bg); color: #fff; }
    .tapin-btn--warning { background: var(--tapin-warning-bg); color: #fff; }
    .tapin-btn--ghost   { background: var(--tapin-ghost-bg); color: var(--tapin-text-dark); }
    .tapin-cat-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .tapin-cat-chip { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--tapin-border-color); border-radius: 999px; padding: 6px 12px; background: #fff; cursor: pointer; user-select: none; }
    .tapin-cat-chip input { accent-color: var(--tapin-primary-color); }
    @media (max-width: 768px) {
        .tapin-card__header { flex-direction: column; }
        .tapin-columns-2 { grid-template-columns: 1fr; }
        .tapin-actions { flex-direction: column; align-items: stretch; }
        .tapin-btn { width: 100%; text-align: center; }
    }';
}

/* =========================================================
 * Time helpers (统一!)
 * ========================================================= */
function tapin_wp_tz() { return wp_timezone(); } // WP >= 5.3

/** parse a local (datetime-local) string to UTC ts */
function tapin_local_str_to_utc_ts($val){
    $val = trim((string)$val);
    if ($val === '') return 0;
    try { $dt = new DateTime($val, tapin_wp_tz()); return $dt->getTimestamp(); }
    catch(Exception $e){ return 0; }
}

/** format UTC ts to local string for input[type=datetime-local] */
function tapin_ts_to_local_input($ts){
    if (empty($ts)) return '';
    $dt = new DateTime('@'.intval($ts));
    $dt->setTimezone(tapin_wp_tz());
    return $dt->format('Y-m-d\TH:i');
}

/** format UTC ts to local pretty string */
function tapin_fmt_local($ts, $format = ''){
    if (!$format) $format = get_option('date_format').' H:i';
    return esc_html( wp_date($format, intval($ts), tapin_wp_tz()) );
}

/** read event_date meta (stored as LOCAL string) -> UTC ts */
function tapin_get_event_ts($pid){
    $local = get_post_meta($pid, TAPIN_META_EVENT_DATE, true);
    if (!$local) return 0;
    return tapin_local_str_to_utc_ts($local);
}

/* =========================================================
 * Sale windows: helpers + price filters
 * ========================================================= */

/** Return sale windows array (sorted, safe). */
function tapin_get_sale_windows($product_id){
    $w = get_post_meta($product_id, TAPIN_META_SALE_WINDOWS, true);
    $w = is_array($w) ? $w : [];
    usort($w, function($a,$b){ return intval($a['start']??0) <=> intval($b['start']??0); });
    return $w;
}

/** Parse sale windows from POST arrays with prefix: _start[], _end[], _price[] */
function tapin_parse_sale_windows_from_post($prefix='sale_w'){
    $starts = isset($_POST["{$prefix}_start"]) ? (array)$_POST["{$prefix}_start"] : [];
    $ends   = isset($_POST["{$prefix}_end"])   ? (array)$_POST["{$prefix}_end"]   : [];
    $prices = isset($_POST["{$prefix}_price"]) ? (array)$_POST["{$prefix}_price"] : [];

    $out = [];
    $count = max(count($starts), count($prices));
    for ($i=0; $i<$count; $i++){
        $price = isset($prices[$i]) ? floatval(str_replace(',', '.', $prices[$i])) : 0;
        $start = isset($starts[$i]) ? tapin_local_str_to_utc_ts($starts[$i]) : 0;
        $end   = isset($ends[$i])   ? tapin_local_str_to_utc_ts($ends[$i])   : 0;
        if ($price > 0 && $start > 0 && ($end === 0 || $end > $start)){
            $out[] = ['start'=>$start, 'end'=>$end, 'price'=>$price];
        }
    }
    usort($out, function($a,$b){ return $a['start'] <=> $b['start']; });
    return $out;
}

/** Save sale windows safely. */
function tapin_apply_product_sale_windows($pid, $windows){
    if (!is_array($windows)) return;
    $norm = [];
    foreach ($windows as $w){
        $norm[] = [
            'start' => intval($w['start'] ?? 0),
            'end'   => intval($w['end'] ?? 0),
            'price' => floatval($w['price'] ?? 0),
        ];
    }
    usort($norm, function($a,$b){ return $a['start'] <=> $b['start']; });
    update_post_meta($pid, TAPIN_META_SALE_WINDOWS, $norm);
}

/** Find active window by current UTC time. */
function tapin_find_active_sale_window($product_id){
    $now = time(); // UTC!
    foreach (tapin_get_sale_windows($product_id) as $w){
        $s = intval($w['start']); $e = intval($w['end']);
        if ($s <= $now && ($e === 0 || $now < $e)) return $w;
    }
    return null;
}

/** Runtime pricing overrides based on active sale window. */
add_filter('woocommerce_product_get_sale_price', function($price, $product){
    $w = tapin_find_active_sale_window($product->get_id());
    return $w ? (string)$w['price'] : $price;
}, 20, 2);

add_filter('woocommerce_product_get_price', function($price, $product){
    $w = tapin_find_active_sale_window($product->get_id());
    if ($w) return (string)$w['price'];
    $reg = $product->get_regular_price();
    return $reg !== '' ? $reg : $price;
}, 20, 2);

add_filter('woocommerce_product_is_on_sale', function($is_on_sale, $product){
    return $is_on_sale || (bool) tapin_find_active_sale_window($product->get_id());
}, 20, 2);

/** Repeater UI renderer used in forms. */
function tapin_render_sale_windows_repeater($windows = [], $name_prefix='sale_w'){
    $fmt = function($ts){ return tapin_ts_to_local_input(intval($ts)); }; ?>
    <style>
      .tapin-sale-w{border:1px solid var(--tapin-border-color);border-radius:12px;padding:12px}
      .tapin-sale-w__row{display:grid;grid-template-columns:1fr 1fr 160px 40px;gap:10px;margin-bottom:10px}
      .tapin-sale-w__row:last-child{margin-bottom:0}
      .tapin-sale-w__remove{width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--tapin-border-color);border-radius:8px;background:#fff;cursor:pointer}
      .tapin-sale-w__add{margin-top:10px}
      @media(max-width:640px){.tapin-sale-w__row{grid-template-columns:1fr}}
    </style>
    <div class="tapin-form-row">
        <label>חלונות הנחה (אופציונלי)</label>
        <div class="tapin-sale-w" data-prefix="<?php echo esc_attr($name_prefix); ?>">
            <div class="tapin-sale-w__rows">
                <?php if (empty($windows)) $windows = [['start'=>0,'end'=>0,'price'=>'']]; ?>
                <?php foreach ($windows as $w): ?>
                <div class="tapin-sale-w__row">
                    <input type="datetime-local" name="<?php echo esc_attr($name_prefix); ?>_start[]" value="<?php echo esc_attr($fmt(intval($w['start']??0))); ?>" placeholder="התחלה">
                    <input type="datetime-local" name="<?php echo esc_attr($name_prefix); ?>_end[]"   value="<?php echo esc_attr($fmt(intval($w['end']??0))); ?>"   placeholder="סיום (אופציונלי)">
                    <input type="number" step="0.01" min="0" name="<?php echo esc_attr($name_prefix); ?>_price[]" value="<?php echo esc_attr($w['price']??''); ?>" placeholder="מחיר הנחה">
                    <button type="button" class="tapin-sale-w__remove" aria-label="הסר">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="tapin-btn tapin-btn--ghost tapin-sale-w__add">+ הוספת חלון</button>
            <div style="font-size:12px;color:var(--tapin-text-light);margin-top:6px">אם תשאירו “סיום” ריק – החלון יהיה פתוח עד מועד האירוע.</div>
        </div>
    </div>
    <script>
    (function(){
      function pad(n){return (n<10?'0':'')+n}
      function nextStartFrom(prevEnd){
        if(!prevEnd) return '';
        var d=new Date(prevEnd);
        if(isNaN(d.getTime())) return '';
        return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
      }
      document.querySelectorAll('.tapin-sale-w').forEach(function(box){
        var rows = box.querySelector('.tapin-sale-w__rows');
        box.addEventListener('click', function(e){
          if(e.target.classList.contains('tapin-sale-w__add')){
            var last = rows.querySelector('.tapin-sale-w__row:last-child');
            var lastEnd = last ? last.querySelector('input[name$="_end[]"]').value : '';
            var prefix = box.getAttribute('data-prefix');
            var wrap = document.createElement('div');
            wrap.className='tapin-sale-w__row';
            wrap.innerHTML =
              '<input type="datetime-local" name="'+prefix+'_start[]" value="'+nextStartFrom(lastEnd)+'" placeholder="התחלה">'+
              '<input type="datetime-local" name="'+prefix+'_end[]" value="" placeholder="סיום (אופציונלי)">'+
              '<input type="number" step="0.01" min="0" name="'+prefix+'_price[]" value="" placeholder="מחיר הנחה">'+
              '<button type="button" class="tapin-sale-w__remove" aria-label="הסר">&times;</button>';
            rows.appendChild(wrap);
          }
          if(e.target.classList.contains('tapin-sale-w__remove')){
            var r = e.target.closest('.tapin-sale-w__row');
            if(r && rows.children.length>1) r.remove();
          }
        });
      });
    })();
    </script>
    <?php
}

/* =========================================================
 * Utility & business helpers
 * ========================================================= */
function tapin_is_manager(){
    $u = wp_get_current_user();
    return is_user_logged_in() && ( current_user_can('manage_woocommerce') || in_array('owner', (array)$u->roles, true) );
}
function tapin_fmt_price_val($v){ return function_exists('wc_format_decimal') ? wc_format_decimal($v) : floatval($v); }
function tapin_get_cat_options(){
    $terms = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
    if (is_wp_error($terms)) $terms = [];
    $out = [];
    foreach($terms as $t){ if($t->slug!=='pending-events') $out[$t->slug]=$t->name; }
    return $out;
}
function tapin_cat_slugs_to_ids($slugs){
    $ids = [];
    if (!$slugs) return $ids;
    foreach((array)$slugs as $s){
        $term = get_term_by('slug', sanitize_title($s), 'product_cat');
        if ($term && !is_wp_error($term)) $ids[] = (int)$term->term_id;
    }
    return array_values(array_unique($ids));
}

/* =========================================================
 * Purchasable gate + product page notice
 * ========================================================= */
add_filter('woocommerce_is_purchasable', function($purchasable, $product){
    $pid   = $product->get_id();
    $event = tapin_get_event_ts($pid); // UTC
    $paused = get_post_meta($pid, TAPIN_META_PAUSED, true);
    if ($paused === 'yes') return false;
    if ($event && $event <= time()) return false; // compare UTC to UTC
    return $purchasable;
}, 10, 2);

add_action('woocommerce_single_product_summary', function(){
    global $product;
    if (!$product) return;
    $pid = $product->get_id();
    $event = tapin_get_event_ts($pid);
    $paused = get_post_meta($pid, TAPIN_META_PAUSED, true);

    if ($paused === 'yes') {
        echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">המכירה לאירוע זה הושהתה זמנית.</div>';
    } elseif ($event && $event <= time()) {
        echo '<div class="woocommerce-info" style="direction:rtl;text-align:right">האירוע הסתיים – המכירה נסגרה.</div>';
    }
}, 6);

/* =========================================================
 * Apply product fields (now supports sale_windows)
 * ========================================================= */
function tapin_apply_product_fields($pid, $arr){
    $title = sanitize_text_field($arr['title'] ?? '');
    $desc  = wp_kses_post($arr['desc'] ?? '');
    $price = ($arr['price']!=='' ? tapin_fmt_price_val($arr['price']) : '');
    $stock = isset($arr['stock']) && $arr['stock'] !== '' ? absint($arr['stock']) : null;
    $event_dt_local = sanitize_text_field($arr['event_dt'] ?? '');
    $event_ts = $event_dt_local ? tapin_local_str_to_utc_ts($event_dt_local) : 0;

    if ($title !== '') wp_update_post(['ID'=>$pid,'post_title'=>$title]);
    if ($desc  !== '') wp_update_post(['ID'=>$pid,'post_content'=>$desc]);

    if ($price !== '') {
        update_post_meta($pid,'_regular_price',$price);
        update_post_meta($pid,'_price',$price);
    }

    if ($stock !== null && $stock >= 0) {
        update_post_meta($pid, '_manage_stock', 'yes');
        update_post_meta($pid, '_stock', $stock);
    }

    // Remove legacy one-off sale meta
    delete_post_meta($pid,'_sale_price');
    delete_post_meta($pid,'_sale_price_dates_from');
    delete_post_meta($pid,'_sale_price_dates_to');

    // Save sale windows only if provided
    if (array_key_exists('sale_windows', $arr) && is_array($arr['sale_windows'])) {
        tapin_apply_product_sale_windows($pid, $arr['sale_windows']);
    }

    if ($event_ts) {
        // store as LOCAL formatted string (human-friendly) but parsed/compared as UTC elsewhere
        $local_str = wp_date('Y-m-d H:i:s', $event_ts, tapin_wp_tz());
        update_post_meta($pid, TAPIN_META_EVENT_DATE, $local_str);
    }

    if (!empty($arr['image_field'])) {
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        $att_id = media_handle_upload($arr['image_field'], $pid);
        if (!is_wp_error($att_id)) set_post_thumbnail($pid,$att_id);
    }
}

/* =========================================================
 * Unified fields renderer (DRY)
 * ========================================================= */
function tapin_render_event_edit_form_fields($product_id, $options = []) {
    $defaults = [
        'name_prefix' => 'sale_w',
        'show_image'  => true,
    ];
    $opts = wp_parse_args($options, $defaults);

    $post = get_post($product_id);
    if (!$post) return;

    $event_dt_local = get_post_meta($product_id, TAPIN_META_EVENT_DATE, true);
    $reg_price      = get_post_meta($product_id, '_regular_price', true);
    $stock          = get_post_meta($product_id, '_stock', true);
    $windows        = tapin_get_sale_windows($product_id);

    $event_input_val = '';
    if ($event_dt_local) {
        try { $event_input_val = (new DateTime($event_dt_local, tapin_wp_tz()))->format('Y-m-d\TH:i'); }
        catch(Exception $e){ $event_input_val = ''; }
    } ?>
    <div class="tapin-form-row">
        <label>כותרת</label>
        <input type="text" name="title" value="<?php echo esc_attr($post->post_title); ?>">
    </div>
    <div class="tapin-form-row">
        <label>תיאור</label>
        <textarea name="desc" rows="4"><?php echo esc_textarea($post->post_content); ?></textarea>
    </div>
    <div class="tapin-columns-2">
        <div class="tapin-form-row">
            <label>מחיר</label>
            <input type="number" name="price" step="0.01" min="0" value="<?php echo esc_attr($reg_price); ?>">
        </div>
        <div class="tapin-form-row">
            <label>כמות כרטיסים</label>
            <input type="number" name="stock" step="1" min="0" value="<?php echo esc_attr($stock); ?>">
        </div>
    </div>

    <?php tapin_render_sale_windows_repeater($windows, $opts['name_prefix']); ?>

    <div class="tapin-form-row">
        <label>מועד האירוע</label>
        <input type="datetime-local" name="event_dt" value="<?php echo esc_attr($event_input_val); ?>">
    </div>
    <?php if ($opts['show_image']) : ?>
    <div class="tapin-form-row">
        <label>תמונה</label>
        <input type="file" name="image" accept="image/*">
    </div>
    <?php endif;
}

/* =========================================================
 * Producer center: actions handler (DRY)
 * ========================================================= */
function tapin_handle_producer_center_actions() {
    if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['tapin_pe_nonce']) || !wp_verify_nonce($_POST['tapin_pe_nonce'],'tapin_pe_action')) {
        return '';
    }
    $u = wp_get_current_user();
    $pid = (int)($_POST['pid'] ?? 0);
    $p = $pid ? get_post($pid) : null;
    if (!$p || (int)$p->post_author !== (int)$u->ID || get_post_type($pid)!=='product') return '';

    // Which action?
    $action = '';
    foreach (['save_pending','request_edit','cancel_request'] as $key) {
        if (isset($_POST[$key])) { $action = $key; break; }
    }
    if ($action==='') return '';

    switch ($action) {
        case 'save_pending':
            $lock_key = 'tapin_edit_pending_'.$pid.'_'.$u->ID;
            if (get_transient($lock_key)) return '<div class="tapin-notice tapin-notice--error">כבר התקבלה שמירה דומה. נסו שוב בעוד רגע.</div>';
            set_transient($lock_key,1,5);
            $arr = [
                'title'=>$_POST['title'] ?? '',
                'desc'=>$_POST['desc'] ?? '',
                'price'=>$_POST['price'] ?? '',
                'stock'=>$_POST['stock'] ?? '',
                'event_dt'=>$_POST['event_dt'] ?? '',
                'image_field'=>'image',
                'sale_windows'=> tapin_parse_sale_windows_from_post('sale_w')
            ];
            tapin_apply_product_fields($pid,$arr);
            return '<div class="tapin-notice tapin-notice--success">הטופס עודכן.</div>';

        case 'request_edit':
            $lock_key = 'tapin_editreq_'.$pid.'_'.$u->ID;
            if (get_transient($lock_key)) return '<div class="tapin-notice tapin-notice--error">בקשה דומה התקבלה זה עתה.</div>';
            set_transient($lock_key,1,60);
            $req = [
                'by'=>$u->ID,
                'at'=>time(),
                'data'=>[
                    'title'=>sanitize_text_field($_POST['title'] ?? ''),
                    'desc'=>wp_kses_post($_POST['desc'] ?? ''),
                    'price'=>($_POST['price'] ?? ''),
                    'stock'=>($_POST['stock'] ?? ''),
                    'event_dt'=>sanitize_text_field($_POST['event_dt'] ?? ''),
                    'sale_windows'=> tapin_parse_sale_windows_from_post('sale_w')
                ]
            ];
            if (!empty($_FILES['image']['name'])) {
                require_once ABSPATH.'wp-admin/includes/file.php';
                require_once ABSPATH.'wp-admin/includes/media.php';
                require_once ABSPATH.'wp-admin/includes/image.php';
                $att = media_handle_upload('image', 0);
                if (!is_wp_error($att)) $req['data']['new_image_id'] = (int)$att;
            }
            update_post_meta($pid, TAPIN_META_EDIT_REQ, $req);
            return '<div class="tapin-notice tapin-notice--success">בקשת העריכה נשלחה וממתינה לאישור מנהל.</div>';

        case 'cancel_request':
            delete_post_meta($pid, TAPIN_META_EDIT_REQ);
            return '<div class="tapin-notice tapin-notice--warning">בקשת העריכה בוטלה.</div>';
    }
    return '';
}

/* =========================================================
 * Admin center: actions handler (DRY)
 * ========================================================= */
function tapin_handle_admin_center_actions($atts = []) {
    if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['tapin_admin_nonce']) || !wp_verify_nonce($_POST['tapin_admin_nonce'],'tapin_admin_action')) {
        return '';
    }
    $pid = (int)($_POST['pid'] ?? 0);
    $p = $pid ? get_post($pid) : null;
    if (!$p || get_post_type($pid)!=='product') return '';

    $action_key = 'tapin_admin_action_pid_'.$pid.'_'.md5(serialize($_POST));
    if (get_transient($action_key)) return '<div class="tapin-notice tapin-notice--error">הפעולה כבר בוצעה. הימנע/י מלחיצה כפולה.</div>';
    set_transient($action_key, 1, 5);

    $action = '';
    foreach (['approve_new','quick_save','approve_edit','reject_edit','trash','pause_sale','resume_sale'] as $key) {
        if (isset($_POST[$key])) { $action = $key; break; }
    }

    switch ($action) {
        case 'approve_new':
            $cats_slugs = isset($_POST['cats']) ? array_map('sanitize_title',(array)$_POST['cats']) : [];
            $term_ids = tapin_cat_slugs_to_ids($cats_slugs);
            if (!$term_ids) {
                delete_transient($action_key);
                return '<div class="tapin-notice tapin-notice--error">יש לבחור לפחות קטגוריה אחת.</div>';
            }
            tapin_apply_product_fields($pid,[
                'title'=>$_POST['title'] ?? '', 'desc'=>$_POST['desc'] ?? '',
                'price'=>$_POST['price'] ?? '', 'stock'=>$_POST['stock'] ?? '',
                'event_dt'=>$_POST['event_dt'] ?? '', 'image_field'=>'image',
                'sale_windows'=> tapin_parse_sale_windows_from_post('sale_w')
            ]);
            wp_set_object_terms($pid, $term_ids, 'product_cat', false);
            $pending = get_term_by('slug','pending-events','product_cat');
            if ($pending && !is_wp_error($pending)) wp_remove_object_terms($pid, [(int)$pending->term_id], 'product_cat');
            wp_set_object_terms($pid,'simple','product_type',false);
            $product = wc_get_product($pid);
            if ($product) {
                $product->set_status('publish');
                $product->set_catalog_visibility('visible');
                $feature = in_array(strtolower((string)($atts['feature_on_approve'] ?? '0')), ['1','yes','true'], true);
                if ($feature) $product->set_featured(true);
                $product->save();
            }
            if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
            clean_post_cache($pid);
            return '<div class="tapin-notice tapin-notice--success">האירוע אושר ופורסם.</div>';

        case 'quick_save':
            tapin_apply_product_fields($pid,[
                'title'=>$_POST['title'] ?? '', 'desc'=>$_POST['desc'] ?? '',
                'price'=>$_POST['price'] ?? '', 'stock'=>$_POST['stock'] ?? '',
                'event_dt'=>$_POST['event_dt'] ?? '', 'image_field'=>'image',
                'sale_windows'=> tapin_parse_sale_windows_from_post('sale_w')
            ]);
            if (isset($_POST['cats'])) {
                $term_ids = tapin_cat_slugs_to_ids((array)$_POST['cats']);
                if ($term_ids) wp_set_object_terms($pid, $term_ids, 'product_cat', false);
            }
            if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
            clean_post_cache($pid);
            return '<div class="tapin-notice tapin-notice--success">הנתונים נשמרו.</div>';

        case 'approve_edit':
            $req = get_post_meta($pid, TAPIN_META_EDIT_REQ, true);
            if (is_array($req) && !empty($req['data'])) {
                $data = $req['data'];
                tapin_apply_product_fields($pid, $data);
                if (!empty($data['sale_windows']) && is_array($data['sale_windows'])) {
                    tapin_apply_product_sale_windows($pid, $data['sale_windows']);
                }
                if (!empty($data['new_image_id'])) set_post_thumbnail($pid,(int)$data['new_image_id']);
                delete_post_meta($pid, TAPIN_META_EDIT_REQ);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                clean_post_cache($pid);
                return '<div class="tapin-notice tapin-notice--success">בקשת העריכה אושרה והוחלה.</div>';
            }
            return '';

        case 'reject_edit':
            delete_post_meta($pid, TAPIN_META_EDIT_REQ);
            return '<div class="tapin-notice tapin-notice--warning">בקשת העריכה נדחתה.</div>';

        case 'trash':
            wp_trash_post($pid);
            if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
            clean_post_cache($pid);
            return '<div class="tapin-notice tapin-notice--warning">האירוע נמחק והועבר לאשפה.</div>';

        case 'pause_sale':
            update_post_meta($pid, TAPIN_META_PAUSED, 'yes');
            if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
            clean_post_cache($pid);
            return '<div class="tapin-notice tapin-notice--warning">מכירת הכרטיסים לאירוע הושהתה.</div>';

        case 'resume_sale':
            delete_post_meta($pid, TAPIN_META_PAUSED);
            if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
            clean_post_cache($pid);
            return '<div class="tapin-notice tapin-notice--success">מכירת הכרטיסים חודשה.</div>';
    }
    return '';
}

/* =========================================================
 * Shortcode: producer_event_request (no DRY changes here)
 * ========================================================= */
add_shortcode('producer_event_request', function($atts){
    $a = shortcode_atts(['redirect'=>''], $atts, 'producer_event_request');

    if (!is_user_logged_in()) {
        status_header(403);
        return '<div class="tapin-notice tapin-notice--error">הדף זמין למפיקים או לבעלי האתר בלבד. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחבר/י</a>.</div>';
    }
    $me = wp_get_current_user();
    $is_admin = current_user_can('manage_woocommerce');
    $role_ok  = array_intersect((array)$me->roles, ['producer','owner']);
    if (!$is_admin && empty($role_ok)) {
        status_header(403);
        return '<div class="tapin-notice tapin-notice--error">אין לך הרשאה לצפות בדף זה.</div>';
    }

    $title_val = isset($_POST['tapin_title']) ? sanitize_text_field(wp_unslash($_POST['tapin_title'])) : '';
    $desc_val  = isset($_POST['tapin_desc'])  ? wp_kses_post(wp_unslash($_POST['tapin_desc'])) : '';
    $price_val = isset($_POST['tapin_price']) ? wc_format_decimal(wp_unslash($_POST['tapin_price'])) : '';
    $event_val = isset($_POST['tapin_event_dt']) ? sanitize_text_field(wp_unslash($_POST['tapin_event_dt'])) : '';
    $stock_val = isset($_POST['tapin_stock']) ? absint($_POST['tapin_stock']) : '';

    // collect sale windows from POST
    $sale_windows_post = ($_SERVER['REQUEST_METHOD'] === 'POST')
        ? tapin_parse_sale_windows_from_post('sale_w')
        : [];

    $msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tapin_event_nonce']) && wp_verify_nonce($_POST['tapin_event_nonce'], 'tapin_event_submit')) {
        $event_ts = $event_val ? tapin_local_str_to_utc_ts($event_val) : 0;
        $unique_key = md5(get_current_user_id().'|'.$title_val.'|'.$event_ts.'|'.$price_val);

        if (get_transient('tapin_submit_'.$unique_key)) {
            $msg = '<div class="tapin-notice tapin-notice--success">הטופס כבר התקבל. הימנע/י מלחיצה כפולה.</div>';
        } elseif (empty($_FILES['tapin_image']['name'])) {
            $msg = '<div class="tapin-notice tapin-notice--error">יש להעלות תמונה.</div>';
        } elseif (!$title_val || !$desc_val || $price_val==='' || !$event_val || $stock_val === '' || $stock_val <= 0) {
            $msg = '<div class="tapin-notice tapin-notice--error">יש למלא כותרת, תיאור, מחיר, כמות כרטיסים ותאריך/שעה.</div>';
        } elseif ($event_ts && $event_ts < time()) {
            $msg = '<div class="tapin-notice tapin-notice--error">מועד האירוע כבר עבר.</div>';
        } else {
            if (empty($msg) && !empty($sale_windows_post) && $event_ts){
                foreach ($sale_windows_post as $w){
                    if (!empty($w['end']) && $w['end'] > $event_ts){
                        $msg = '<div class="tapin-notice tapin-notice--error">חלון הנחה מסתיים אחרי מועד האירוע.</div>';
                        break;
                    }
                }
            }
        }

        if (empty($msg)) {
            set_transient('tapin_submit_'.$unique_key, 1, 60);

            $pid = wp_insert_post([
                'post_type'   => 'product',
                'post_status' => 'pending',
                'post_author' => get_current_user_id(),
                'post_title'  => $title_val,
                'post_content'=> $desc_val,
            ], true);

            if (is_wp_error($pid)) {
                delete_transient('tapin_submit_'.$unique_key);
                $msg = '<div class="tapin-notice tapin-notice--error">שגיאה ביצירת אירוע: '.esc_html($pid->get_error_message()).'</div>';
            } else {
                update_post_meta($pid, '_virtual', 'yes');
                update_post_meta($pid, '_manage_stock', 'yes');
                update_post_meta($pid, '_stock', $stock_val);
                update_post_meta($pid, '_stock_status', 'instock');
                update_post_meta($pid, '_regular_price', $price_val);
                update_post_meta($pid, '_price', $price_val);
                update_post_meta($pid, TAPIN_META_EVENT_DATE, wp_date('Y-m-d H:i:s', $event_ts, tapin_wp_tz()));
                wp_set_object_terms($pid, 'simple', 'product_type', false);

                // remove legacy sale meta and save windows
                delete_post_meta($pid,'_sale_price');
                delete_post_meta($pid,'_sale_price_dates_from');
                delete_post_meta($pid,'_sale_price_dates_to');
                tapin_apply_product_sale_windows($pid, $sale_windows_post);

                if (function_exists('wc_get_product')) {
                    $p = wc_get_product($pid);
                    if ($p) { $p->set_catalog_visibility('visible'); $p->save(); }
                }
                $pending = get_term_by('slug', 'pending-events', 'product_cat');
                if ($pending && !is_wp_error($pending)) {
                    wp_set_object_terms($pid, [(int)$pending->term_id], 'product_cat', false);
                }

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $att_id = media_handle_upload('tapin_image', $pid);
                if (is_wp_error($att_id)) {
                    wp_delete_post($pid, true);
                    delete_transient('tapin_submit_'.$unique_key);
                    $msg = '<div class="tapin-notice tapin-notice--error">שגיאה בהעלאת התמונה: '.esc_html($att_id->get_error_message()).'</div>';
                } else {
                    set_post_thumbnail($pid, $att_id);
                    $target = $a['redirect'] ? esc_url_raw($a['redirect']) : home_url('/');
                    $target = add_query_arg('tapin_thanks', '1', $target);
                    wp_safe_redirect($target);
                    exit;
                }
            }
        }
    }

    ob_start(); ?>
    <style>
        <?php echo tapin_get_shared_css(); ?>
        .tapin-notice { padding: 12px; border-radius: 8px; margin-bottom: 20px; direction: rtl; text-align: right; }
        .tapin-notice--error { background: #fff4f4; border: 1px solid #f3c2c2; }
        .tapin-notice--success { background:#f0fff4; border: 1px solid #b8e1c6; }
        .tapin-notice--warning { background: #fff7ed; border: 1px solid #ffd7b5; }
    </style>
    <div class="tapin-center-container" style="max-width: 780px;">
        <h2 class="tapin-title">יצירת אירוע חדש</h2>
        <?php echo $msg; ?>
        <form id="tapinForm" method="post" enctype="multipart/form-data" class="tapin-card" novalidate>
            <div class="tapin-form-row">
                <label for="tapin_title">כותרת האירוע <span style="color:#e11d48">*</span></label>
                <input id="tapin_title" type="text" name="tapin_title" value="<?php echo esc_attr($title_val); ?>" required>
            </div>
            <div class="tapin-form-row">
                <label for="tapin_desc">תיאור המסיבה <span style="color:#e11d48">*</span></label>
                <textarea id="tapin_desc" name="tapin_desc" rows="6" required><?php echo esc_textarea($desc_val); ?></textarea>
            </div>
            <div class="tapin-form-row">
                <label for="tapin_image">תמונה <span style="color:#e11d48">*</span></label>
                <input id="tapin_image" type="file" name="tapin_image" accept="image/*" required>
            </div>
            <div class="tapin-form-row tapin-columns-2">
                <div>
                    <label for="tapin_price">מחיר לכרטיס <span style="color:#e11d48">*</span></label>
                    <input id="tapin_price" type="number" name="tapin_price" step="0.01" min="0" value="<?php echo esc_attr($price_val); ?>" required>
                </div>
                <div>
                    <label for="tapin_stock">כמות כרטיסים <span style="color:#e11d48">*</span></label>
                    <input id="tapin_stock" type="number" name="tapin_stock" min="1" step="1" value="<?php echo esc_attr($stock_val); ?>" required>
                </div>
            </div>

            <?php tapin_render_sale_windows_repeater($sale_windows_post, 'sale_w'); ?>

            <div class="tapin-form-row">
                <label for="tapin_event_dt">תאריך ושעת האירוע <span style="color:#e11d48">*</span></label>
                <input id="tapin_event_dt" type="datetime-local" name="tapin_event_dt" value="<?php echo esc_attr($event_val); ?>" required>
            </div>
            <?php wp_nonce_field('tapin_event_submit','tapin_event_nonce'); ?>
            <div class="tapin-actions">
                <button id="tapinSubmitBtn" type="submit" class="tapin-btn tapin-btn--primary">שליחה לאישור</button>
            </div>
        </form>
    </div>
    <script>
      (function(){var f=document.getElementById('tapinForm');if(!f)return;f.addEventListener('submit',function(){var b=document.getElementById('tapinSubmitBtn');if(b){b.disabled=true;b.textContent='שולח…';}});})();
    </script>
    <?php
    return ob_get_clean();
});

/* =========================================================
 * Shortcode: producer_events_center (producer dashboard)
 * ========================================================= */
add_shortcode('producer_events_center', function(){
    if (!is_user_logged_in()) {
        status_header(403);
        return '<div class="tapin-notice tapin-notice--error">יש להתחבר למערכת.</div>';
    }
    $u = wp_get_current_user();
    $is_producer = in_array('producer', (array)$u->roles, true) || in_array('owner', (array)$u->roles, true);
    if (!$is_producer) {
        status_header(403);
        return '<div class="tapin-notice tapin-notice--error">הדף זמין למפיקים בלבד.</div>';
    }

    // Handle actions (DRY)
    $msg = tapin_handle_producer_center_actions();

    $pending_q = new WP_Query(['post_type'=>'product','post_status'=>['pending'],'author'=>$u->ID,'posts_per_page'=>-1,'no_found_rows'=>true]);
    $pending_ids = $pending_q->have_posts()?wp_list_pluck($pending_q->posts,'ID'):[];
    $active_q = new WP_Query([
        'post_type'=>'product','post_status'=>['publish'],'author'=>$u->ID,
        'meta_key'=>TAPIN_META_EVENT_DATE,'orderby'=>'meta_value','order'=>'ASC',
        'posts_per_page'=>-1,'no_found_rows'=>true,
        'meta_query'=>[['key'=>TAPIN_META_EVENT_DATE,'compare'=>'>=','value'=>wp_date('Y-m-d H:i:s', time(), tapin_wp_tz()),'type'=>'DATETIME']]
    ]);
    $active_ids = $active_q->have_posts()?wp_list_pluck($active_q->posts,'ID'):[];
    ob_start(); ?>
    <style>
        <?php echo tapin_get_shared_css(); ?>
        .tapin-notice { padding: 12px; border-radius: 8px; margin-bottom: 20px; direction: rtl; text-align: right; }
        .tapin-notice--error { background: #fff4f4; border: 1px solid #f3c2c2; }
        .tapin-notice--success { background:#f0fff4; border: 1px solid #b8e1c6; }
        .tapin-notice--warning { background: #fff7ed; border: 1px solid #ffd7ב5; }
    </style>
    <div class="tapin-center-container">
        <?php echo $msg; ?>

        <h3 class="tapin-title">אירועים ממתינים שלי</h3>
        <?php if ($pending_ids): ?>
        <div class="tapin-form-grid">
            <?php foreach($pending_ids as $pid):
                $thumb = get_the_post_thumbnail_url($pid,'woocommerce_thumbnail');
                if(!$thumb && function_exists('wc_placeholder_img_src')) $thumb = wc_placeholder_img_src();
            ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card">
                <div class="tapin-card__header">
                    <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                    <div style="flex:1;">
                        <h4 class="tapin-card__title"><?php echo esc_html(get_the_title($pid)); ?></h4>
                    </div>
                </div>

                <?php tapin_render_event_edit_form_fields($pid, ['name_prefix'=>'sale_w']); ?>

                <div class="tapin-actions">
                    <button type="submit" name="save_pending" class="tapin-btn tapin-btn--primary">שמירה</button>
                </div>
                <?php wp_nonce_field('tapin_pe_action','tapin_pe_nonce'); ?>
                <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p>אין כרגע אירועים ממתינים.</p>
        <?php endif; ?>

        <h3 class="tapin-title" style="margin-top: 40px;">אירועים פעילים שלי</h3>
        <?php if ($active_ids): ?>
        <div class="tapin-form-grid">
            <?php foreach($active_ids as $pid):
                $thumb= get_the_post_thumbnail_url($pid,'woocommerce_thumbnail');
                if(!$thumb && function_exists('wc_placeholder_img_src')) $thumb = wc_placeholder_img_src();
                $req  = get_post_meta($pid, TAPIN_META_EDIT_REQ, true);
                $is_paused = get_post_meta($pid, TAPIN_META_PAUSED, true) === 'yes';
            ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card <?php if($is_paused) echo 'tapin-card--paused'; ?>">
                <div class="tapin-card__header">
                    <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                    <div style="flex:1;">
                        <h4 class="tapin-card__title">
                            <?php echo esc_html(get_the_title($pid)); ?>
                            <?php if($is_paused): ?><span class="tapin-status-badge tapin-status-badge--paused">— מכירה מושהית</span><?php endif; ?>
                            <?php if($req): ?><span class="tapin-status-badge tapin-status-badge--pending">— בקשת עריכה ממתינה</span><?php endif; ?>
                        </h4>
                    </div>
                </div>

                <?php tapin_render_event_edit_form_fields($pid, ['name_prefix'=>'sale_w']); ?>

                <div class="tapin-actions">
                    <?php if(!$req): ?>
                        <button type="submit" name="request_edit" class="tapin-btn tapin-btn--primary">בקשת עריכה</button>
                    <?php else: ?>
                        <button type="submit" name="cancel_request" class="tapin-btn tapin-btn--ghost">בטל בקשה</button>
                    <?php endif; ?>
                </div>
                <?php wp_nonce_field('tapin_pe_action','tapin_pe_nonce'); ?>
                <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p>אין אירועים פעילים כרגע.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
 * Shortcode: events_admin_center (admin dashboard)
 * ========================================================= */
add_shortcode('events_admin_center', function($atts){
    if (!tapin_is_manager()) {
        status_header(403);
        return '<div class="tapin-notice tapin-notice--error">הדף למנהלים בלבד.</div>';
    }
    $a = shortcode_atts(['feature_on_approve'=>'0'], $atts, 'events_admin_center');

    // Handle actions (DRY)
    $msg = tapin_handle_admin_center_actions($a);

    $fmt_price = function($val){ return function_exists('wc_price') ? wc_price((float)$val) : number_format_i18n((float)$val,2); };

    $pending_q = new WP_Query(['post_type'=>'product','post_status'=>['pending'],'posts_per_page'=>-1,'no_found_rows'=>true]);
    $pending_ids = $pending_q->have_posts()?wp_list_pluck($pending_q->posts,'ID'):[];
    $active_q = new WP_Query(['post_type'=>'product','post_status'=>['publish'],'meta_key'=>TAPIN_META_EVENT_DATE,'orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>TAPIN_META_EVENT_DATE,'compare'=>'>=','value'=>wp_date('Y-m-d H:i:s', time(), tapin_wp_tz()),'type'=>'DATETIME']],'posts_per_page'=>-1,'no_found_rows'=>true]);
    $active_ids = $active_q->have_posts()?wp_list_pluck($active_q->posts,'ID'):[];
    $edit_req_q = new WP_Query(['post_type'=>'product','post_status'=>['publish'],'posts_per_page'=>-1,'no_found_rows'=>true,'meta_query'=>[['key'=>TAPIN_META_EDIT_REQ,'compare'=>'EXISTS']]]);
    $edit_ids = $edit_req_q->have_posts()?wp_list_pluck($edit_req_q->posts,'ID'):[];
    
    ob_start(); ?>
    <style>
        <?php echo tapin_get_shared_css(); ?>
        .tapin-notice { padding: 12px; border-radius: 8px; margin-bottom: 20px; direction: rtl; text-align: right; }
        .tapin-notice--error { background: #fff4פ4; border: 1px solid #f3c2c2; }
        .tapin-notice--success { background:#f0fff4; border: 1px solid #b8e1c6; }
        .tapin-notice--warning { background: #fff7ed; border: 1px solid #ffd7ב5; }
        .tapin-edit-request-grid { padding-top: 10px; }
        .tapin-edit-request-grid > div { margin-top: 10px; }
        .tapin-edit-request-grid strong { font-weight: 700; color: var(--tapin-text-dark); display: block; margin-bottom: 6px;}
        .tapin-edit-request-grid span { display: block; color: var(--tapin-text-light); font-size: 0.9rem; }
    </style>
    <div class="tapin-center-container">
        <?php echo $msg; ?>

        <h3 class="tapin-title">אירועים ממתינים לאישור</h3>
        <div class="tapin-form-grid">
            <?php if($pending_ids): foreach($pending_ids as $pid):
                $author = get_userdata((int)get_post_field('post_author',$pid));
                $thumb = get_the_post_thumbnail_url($pid,'thumbnail') ?: includes_url('images/media/default.png');
            ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card">
                <div class="tapin-card__header">
                    <a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank" rel="noopener"><img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt=""></a>
                    <div style="flex:1;">
                        <h4 class="tapin-card__title"><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($pid)); ?></a></h4>
                        <div class="tapin-card__meta">מפיק: <?php echo esc_html($author ? $author->display_name : ''); ?></div>
                    </div>
                </div>

                <?php tapin_render_event_edit_form_fields($pid, ['name_prefix'=>'sale_w']); ?>

                <div class="tapin-form-row">
                    <label>קטגוריות</label>
                    <div class="tapin-cat-list">
                        <?php foreach(tapin_get_cat_options() as $slug=>$name): ?>
                        <label class="tapin-cat-chip"><input type="checkbox" name="cats[]" value="<?php echo esc_attr($slug); ?>"> <span><?php echo esc_html($name); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tapin-actions">
                    <button type="submit" name="approve_new" class="tapin-btn tapin-btn--primary">אישור ופרסום</button>
                    <button type="submit" name="quick_save" class="tapin-btn tapin-btn--ghost">שמירה</button>
                    <button type="submit" name="trash" class="tapin-btn tapin-btn--danger" onclick="return confirm('להעביר לאשפה?');">מחיקה</button>
                </div>
                <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
                <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; else: ?><p>אין אירועים ממתינים.</p><?php endif; ?>
        </div>

        <h3 class="tapin-title" style="margin-top: 40px;">בקשות עריכה</h3>
        <div class="tapin-form-grid">
            <?php if($edit_ids): foreach($edit_ids as $pid):
                $req = get_post_meta($pid, TAPIN_META_EDIT_REQ, true);
                $data = is_array($req)?($req['data']??[]):[];
                $thumb = get_the_post_thumbnail_url($pid,'thumbnail') ?: includes_url('images/media/default.png');
                $event_dt_local = get_post_meta($pid, TAPIN_META_EVENT_DATE, true);
                $reg  = get_post_meta($pid,'_regular_price',true);
                $stock= get_post_meta($pid,'_stock',true);
            ?>
            <form method="post" class="tapin-card">
                <div class="tapin-card__header">
                    <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                    <div style="flex:1;"><h4 class="tapin-card__title"><?php echo esc_html(get_the_title($pid)); ?></h4></div>
                </div>
                <div class="tapin-columns-2 tapin-edit-request-grid">
                    <div>
                        <strong>נתונים נוכחיים</strong>
                        <span>כותרת: <?php echo esc_html(get_the_title($pid)); ?></span>
                        <span>מחיר: <?php echo $fmt_price($reg); ?></span>
                        <span>כמות: <?php echo esc_html($stock); ?></span>
                        <span>מועד: <?php echo $event_dt_local?esc_html(wp_date(get_option('date_format').' H:i', tapin_local_str_to_utc_ts($event_dt_local), tapin_wp_tz())):'—'; ?></span>
                    </div>
                    <div>
                        <strong>נתונים מבוקשים</strong>
                        <span>כותרת: <?php echo esc_html($data['title'] ?? ''); ?></span>
                        <span>מחיר: <?php echo esc_html($data['price'] ?? ''); ?></span>
                        <span>כמות: <?php echo esc_html($data['stock'] ?? ''); ?></span>
                        <span>מועד: <?php echo !empty($data['event_dt'])?esc_html(wp_date(get_option('date_format').' H:i', tapin_local_str_to_utc_ts($data['event_dt']), tapin_wp_tz())):'—'; ?></span>
                    </div>
                </div>
                <div class="tapin-actions">
                    <button type="submit" name="approve_edit" class="tapin-btn tapin-btn--primary">אישור בקשה</button>
                    <button type="submit" name="reject_edit" class="tapin-btn tapin-btn--danger" onclick="return confirm('לדחות את הבקשה?');">דחייה</button>
                </div>
                <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
                <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; else: ?><p>אין בקשות עריכה.</p><?php endif; ?>
        </div>

        <h3 class="tapin-title" style="margin-top: 40px;">אירועים פעילים</h3>
        <div class="tapin-form-grid">
            <?php if($active_ids): foreach($active_ids as $pid):
                $thumb = get_the_post_thumbnail_url($pid,'thumbnail') ?: includes_url('images/media/default.png');
                $current_terms = get_the_terms($pid,'product_cat');
                $selected_slugs = $current_terms && !is_wp_error($current_terms) ? wp_list_pluck($current_terms, 'slug') : [];
                $is_paused = get_post_meta($pid, TAPIN_META_PAUSED, true) === 'yes';
            ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card <?php if($is_paused) echo 'tapin-card--paused'; ?>">
                <div class="tapin-card__header">
                    <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                    <div style="flex:1;">
                        <h4 class="tapin-card__title">
                            <a href="<?php echo esc_url(get_edit_post_link($pid)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($pid)); ?></a>
                            <?php if ($is_paused): ?><span class="tapin-status-badge tapin-status-badge--paused">מכירה מושהית</span><?php endif; ?>
                        </h4>
                    </div>
                </div>

                <?php tapin_render_event_edit_form_fields($pid, ['name_prefix'=>'sale_w']); ?>

                <div class="tapin-form-row">
                    <label>קטגוריות</label>
                    <div class="tapin-cat-list">
                        <?php foreach(tapin_get_cat_options() as $slug=>$name): ?>
                        <label class="tapin-cat-chip"><input type="checkbox" name="cats[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected_slugs)); ?>> <span><?php echo esc_html($name); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="tapin-actions">
                    <button type="submit" name="quick_save" class="tapin-btn tapin-btn--ghost">שמירה</button>
                    <?php if ($is_paused): ?>
                        <button type="submit" name="resume_sale" class="tapin-btn tapin-btn--primary">חידוש מכירה</button>
                    <?php else: ?>
                        <button type="submit" name="pause_sale" class="tapin-btn tapin-btn--warning" onclick="return confirm('האם להשהות את מכירת הכרטיסים לאירוע זה?');">השהיית מכירה</button>
                    <?php endif; ?>
                    <button type="submit" name="trash" class="tapin-btn tapin-btn--danger" onclick="return confirm('האם למחוק את האירוע? הפעולה תעביר אותו לאשפה.');">מחיקה</button>
                </div>
                <?php wp_nonce_field('tapin_admin_action','tapin_admin_nonce'); ?>
                <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">
            </form>
            <?php endforeach; else: ?><p>אין אירועים פעילים.</p><?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================================================
 * Front-end "thanks" toast after redirect
 * ========================================================= */
function tapin_render_thanks_notice(){
    static $printed = false;
    if ($printed || is_admin() || !isset($_GET['tapin_thanks'])) return;
    $printed = true; ?>
    <div dir="rtl" style="position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;background:#f0fff4;border:1px solid #b8e1c6;color:#065f46;padding:12px 16px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);font-family:inherit;text-align:right">
        תודה! הבקשה נשלחה וממתינה לאישור מנהל.
    </div>
    <?php
}
add_action('wp_body_open', 'tapin_render_thanks_notice');
add_action('wp_footer', 'tapin_render_thanks_notice');

/* =========================================================
 * Product page: show sale windows as separate cards
 * ========================================================= */
function tapin_render_sale_windows_cards () {
    global $product;
    if (!$product) return;

    $pid      = $product->get_id();
    $windows  = tapin_get_sale_windows($pid);
    if (empty($windows)) return;

    $now      = time();                // UTC
    $event_ts = tapin_get_event_ts($pid); // UTC

    echo '<style>
      .tapin-pw{direction:rtl;text-align:right;margin:10px 0 16px}
      .tapin-pw__title{font-weight:800;color:var(--tapin-primary-color);margin:0 0 10px;font-size:16px;text-align:center}
      .tapin-pw__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
      .tapin-pw-card{background:#fff;border:1px solid var(--tapin-border-color);border-radius:14px;padding:12px 14px;box-shadow:var(--tapin-card-shadow);display:flex;flex-direction:column;gap:8px}
      .tapin-pw-card--current{box-shadow:0 0 0 2px rgba(22,163,74,.15) inset}
      .tapin-pw-card--upcoming{box-shadow:0 0 0 2px rgba(14,165,233,.12) inset}
      .tapin-pw-card--past{opacity:.7}
      .tapin-pw-card__row{display:flex;align-items:center;justify-content:space-between;gap:8px}
      .tapin-pw-card__price{font-weight:800;font-size:1.15rem}
      .tapin-pw-card__dates{font-size:.95rem;color:var(--tapin-text-light);line-height:1.3}
      .tapin-pw-card__badge{font-size:.8rem;font-weight:700;white-space:nowrap}
      .tapin-pw-card--current .tapin-pw-card__badge{color:var(--tapin-success-bg)}
      .tapin-pw-card--upcoming .tapin-pw-card__badge{color:var(--tapin-info-bg)}
      .tapin-pw-card--past .tapin-pw-card__badge{color:#94a3b8;text-decoration:line-through}
      .tapin-pw__hint{font-size:.8rem;color:var(--tapin-text-light);margin-top:6px;text-align:center}
    </style>';

    echo '<div class="tapin-pw">';
    echo   '<div class="tapin-pw__title">מחירי מכירה לפי התאריך</div>';
    echo   '<div class="tapin-pw__grid">';

    foreach ($windows as $w) {
        $s = intval($w['start'] ?? 0);
        $e = intval($w['end']   ?? 0);
        if (!$e && $event_ts) $e = $event_ts;

        $state = 'upcoming';
        if ($s <= $now && ($e === 0 || $now < $e)) {
            $state = 'current';
        } elseif ($e && $now >= $e) {
            $state = 'past';
        }

        $start_str = $s ? tapin_fmt_local($s) : '—';
        $end_str   = $e ? tapin_fmt_local($e) : 'מועד האירוע';
        $badge     = ($state==='current' ? 'מחיר נוכחי' : ($state==='upcoming' ? 'בקרוב' : 'עבר'));

        echo '<div class="tapin-pw-card tapin-pw-card--'.$state.'">';
        echo   '<div class="tapin-pw-card__row">';
        echo     '<span class="tapin-pw-card__price">'.wc_price((float)$w['price']).'</span>';
        echo     '<span class="tapin-pw-card__badge">'.$badge.'</span>';
        echo   '</div>';
        echo   '<div class="tapin-pw-card__dates">מ־ '.$start_str.'<br>עד '.$end_str.'</div>';
        echo '</div>';
    }

    echo   '</div>'; // grid
    echo   '<div class="tapin-pw__hint">המחיר מתעדכן אוטומטית לפי התאריך שנקבע.</div>';
    echo '</div>';
}
add_action('woocommerce_single_product_summary', 'tapin_render_sale_windows_cards', 12);
