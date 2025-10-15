<?php
if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/*
 * =========================================================
 * Producer Flow - UM-ready (no Hebrew comments)
 * - Avatar & Cover: sync into UM folder/meta (removes the "+")
 * - Primary storage via WP Media; UM can use filename in /uploads/ultimatemember/<user_id>/
 * - Filters prefer UM meta, fall back to attachment IDs
 * - Clear UM cache after updates
 * =========================================================
 */

/* --- START: Shared CSS Function (SCOPED) --- */
function tapin_get_shared_css_for_producer() {
    return '
    .tapin-scope{ --tapin-radius-md:12px; --tapin-radius-lg:16px; --tapin-primary-color:#2a1a5e; --tapin-text-dark:#1f2937; --tapin-text-light:#334155; --tapin-border-color:#e5e7eb; --tapin-success-bg:#16a34a; --tapin-danger-bg:#ef4444; --tapin-warning-bg:#f59e0b; --tapin-info-bg:#0ea5e9; --tapin-ghost-bg:#f1f5f9; --tapin-card-shadow:0 4px 12px rgba(2,6,23,.05); }
    .tapin-scope .tapin-center-container { max-width:1100px; margin-inline:auto; direction:rtl; text-align:right; }
    .tapin-scope .tapin-center-container *, .tapin-scope .tapin-center-container *::before, .tapin-scope .tapin-center-container *::after { box-sizing:border-box; }
    .tapin-scope .tapin-title { font-size:28px; font-weight:800; color:var(--tapin-primary-color); margin:14px 0 20px; }
    .tapin-scope .tapin-card { background:#fff; border:1px solid var(--tapin-border-color); border-radius:var(--tapin-radius-lg); padding:20px; box-shadow:var(--tapin-card-shadow); }
    .tapin-scope .tapin-form-row { margin-bottom:16px; }
    .tapin-scope .tapin-form-row:last-child { margin-bottom:0; }
    .tapin-scope .tapin-form-row label { display:block; margin-bottom:6px; font-weight:700; color:var(--tapin-text-dark); }
    .tapin-scope .tapin-form-row input[type="text"], 
    .tapin-scope .tapin-form-row input[type="email"], 
    .tapin-scope .tapin-form-row input[type="file"], 
    .tapin-scope .tapin-form-row input[type="url"], 
    .tapin-scope .tapin-form-row textarea { width:100%; padding:12px 14px; border:1px solid var(--tapin-border-color); border-radius:var(--tapin-radius-md); background:#fff; transition:border-color .2s, box-shadow .2s; }
    .tapin-scope .tapin-form-row input:focus, 
    .tapin-scope .tapin-form-row textarea:focus { border-color:var(--tapin-primary-color); box-shadow:0 0 0 3px rgba(42,26,94,0.1); outline:none; }
    .tapin-scope .tapin-columns-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .tapin-scope .tapin-actions { display:flex; gap:12px; align-items:center; margin-top:20px; flex-wrap:wrap; }
    .tapin-scope .tapin-btn { padding:12px 20px; border-radius:var(--tapin-radius-md); border:0; cursor:pointer; font-weight:600; transition:opacity .2s; font-size:1rem; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; }
    .tapin-scope .tapin-btn:hover { opacity:.85; }
    .tapin-scope .tapin-btn--primary { background:var(--tapin-success-bg); color:#fff; }
    .tapin-scope .tapin-btn--danger { background:var(--tapin-danger-bg); color:#fff; }
    .tapin-scope .tapin-btn--warning { background:var(--tapin-warning-bg); color:#fff; }
    .tapin-scope .tapin-btn--ghost { background:var(--tapin-ghost-bg); color:var(--tapin-text-dark); }
    .tapin-scope .tapin-notice { padding:12px; border-radius:8px; margin-bottom:20px; direction:rtl; text-align:right; }
    .tapin-scope .tapin-notice--error { background:#fff4f4; border:1px solid #f3c2c2; color:#7f1d1d; }
    .tapin-scope .tapin-notice--success { background:#f0fff4; border:1px solid #b8e1c6; color:#065f46; }
    .tapin-scope .tapin-notice--warning { background:#fff7ed; border:1px solid #ffd7b5; color:#854d0e; }
    @media (max-width:768px){
      .tapin-scope .tapin-columns-2 { grid-template-columns:1fr; }
      .tapin-scope .tapin-actions { flex-direction:column; align-items:stretch; }
      .tapin-scope .tapin-btn { width:100%; }
    }';
}
/* --- END: Shared CSS Function --- */

/* === Helpers: attachment URLs (fallbacks) === */
function tapin_producer_avatar_url($user_id, $size = 'medium') {
    $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
    if ($aid) {
        $src = wp_get_attachment_image_src($aid, $size);
        if ($src && !empty($src[0])) return $src[0];
    }
    return '';
}
function tapin_producer_cover_url($user_id, $size = 'large') {
    $cid = (int) get_user_meta($user_id, 'producer_cover_id', true);
    if ($cid) {
        $url = wp_get_attachment_image_url($cid, $size);
        if ($url) return $url;
    }
    return '';
}

/* === Helpers: UM folder sync & URL builders === */
function tapin_um_dir_for_user($user_id){
    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'ultimatemember/' . (int)$user_id;
    return $dir;
}
function tapin_um_url_for_userfile($user_id, $filename){
    $uploads = wp_upload_dir();
    return trailingslashit($uploads['baseurl']) . 'ultimatemember/' . (int)$user_id . '/' . ltrim($filename, '/');
}

/* Copy attachment into UM folder and store filename in UM meta ($kind: profile|cover) */
function tapin_um_sync_media_to_um_folder($attachment_id, $user_id, $kind){
    $attachment_id = (int)$attachment_id;
    $user_id = (int)$user_id;
    if (!$attachment_id || !$user_id) return false;

    $src = get_attached_file($attachment_id);
    if (!$src || !file_exists($src)) return false;

    $ext = pathinfo($src, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : 'jpg';
    $prefix = ($kind === 'cover') ? 'cover' : 'profile';
    $filename = $prefix . '_' . $attachment_id . '_' . time() . '.' . $ext;

    $dest_dir = tapin_um_dir_for_user($user_id);
    if (!is_dir($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }
    $dest = trailingslashit($dest_dir) . $filename;

    if (!@copy($src, $dest)) {
        $contents = @file_get_contents($src);
        if ($contents === false || @file_put_contents($dest, $contents) === false) {
            return false;
        }
    }

    if ($kind === 'cover') {
        update_user_meta($user_id, 'cover_photo', $filename);
        update_user_meta($user_id, 'um_cover_photo', $filename);
    } else {
        update_user_meta($user_id, 'profile_photo', $filename);
        update_user_meta($user_id, 'um_profile_photo', $filename);
    }

    return $filename;
}

/* Build avatar/cover URLs from UM meta with fallbacks */
function tapin_get_um_profile_photo_url($user_id, $size = 'medium'){
    $meta = get_user_meta($user_id, 'profile_photo', true);
    if (is_numeric($meta) && (int)$meta > 0) {
        $src = wp_get_attachment_image_src((int)$meta, $size);
        if ($src && !empty($src[0])) return $src[0];
    } elseif (!empty($meta)) {
        return tapin_um_url_for_userfile($user_id, $meta);
    }
    $meta2 = get_user_meta($user_id, 'um_profile_photo', true);
    if (!empty($meta2) && !is_numeric($meta2)) {
        return tapin_um_url_for_userfile($user_id, $meta2);
    }
    return tapin_producer_avatar_url($user_id, $size);
}
function tapin_get_um_cover_url($user_id, $size = 'full'){
    $meta = get_user_meta($user_id, 'cover_photo', true);
    if (is_numeric($meta) && (int)$meta > 0) {
        $url = wp_get_attachment_image_url((int)$meta, $size);
        if ($url) return $url;
    } elseif (!empty($meta)) {
        return tapin_um_url_for_userfile($user_id, $meta);
    }
    $meta2 = get_user_meta($user_id, 'um_cover_photo', true);
    if (!empty($meta2) && !is_numeric($meta2)) {
        return tapin_um_url_for_userfile($user_id, $meta2);
    }
    return tapin_producer_cover_url($user_id, $size);
}

/* --- START: Socials sync to UM field keys --- */
function tapin_sync_socials_to_um_keys($user_id, $vals){
    $user_id = (int)$user_id;
    if (!$user_id) return;

    $map = [
        'instagram' => ['instagram','instagram_url'],
        'facebook'  => ['facebook','facebook_url'],
        'tiktok'    => ['tiktok','tiktok_url'],
        'youtube'   => ['youtube','youtube_url'],
        'whatsapp'  => ['whatsapp','whatsapp_number','whatsapp_phone','phone_whatsapp'],
    ];

    foreach ($map as $source => $targets){
        $v = isset($vals[$source]) ? trim((string)$vals[$source]) : '';
        if ($source === 'whatsapp') {
            $v = preg_replace('/\D+/', '', $v);
        }
        if ($v !== '') {
            foreach ($targets as $t){
                update_user_meta($user_id, $t, $v);
            }
        }
    }

    if (!empty($vals['website'])) {
        $url = esc_url_raw($vals['website']);
        wp_update_user(['ID'=>$user_id,'user_url'=>$url]);
        update_user_meta($user_id, 'website', $url);
        update_user_meta($user_id, 'website_url', $url);
    }
}
/* --- END: Socials sync --- */

/* Register WooCommerce endpoints */
add_action('init', function () {
    add_rewrite_endpoint('become-producer', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('producer', EP_ROOT | EP_PAGES);
});
add_filter('woocommerce_get_query_vars', function ($vars) {
    $vars['become-producer'] = 'become-producer';
    $vars['producer']        = 'producer';
    return $vars;
});

/* Account menu */
add_filter('woocommerce_account_menu_items', function ($items) {
    $user = wp_get_current_user();
    if (!is_user_logged_in()) return $items;
    $is_producer = in_array('producer', (array)$user->roles, true);
    $status      = get_user_meta($user->ID, 'producer_status', true);
    if ($is_producer) {
        $items['producer'] = 'פרופיל מפיק';
    } elseif ($status === 'pending' || $status === 'rejected') {
        $items['become-producer'] = ($status === 'pending') ? 'בקשת מפיק – ממתין' : 'בקשת מפיק (נדחה)';
    }
    return $items;
});
add_filter('woocommerce_endpoint_become-producer_title', function(){ return 'בקשת מפיק'; });
add_filter('woocommerce_endpoint_producer_title',        function(){ return 'פרופיל מפיק'; });

/* Defaults */
function tapin_producer_fields_defaults($user_id) {
    if (!$user_id) return [];
    $user_data = get_userdata($user_id);
    return [
        'producer_display_name'  => $user_data ? $user_data->display_name : '',
        'producer_about'         => get_user_meta($user_id, 'producer_about', true),
        'producer_address'       => get_user_meta($user_id, 'producer_address', true),
        'producer_phone_public'  => get_user_meta($user_id, 'producer_phone_public', true),
        'producer_phone_private' => get_user_meta($user_id, 'producer_phone_private', true),
        'producer_instagram'     => get_user_meta($user_id, 'producer_instagram', true),
        'producer_facebook'      => get_user_meta($user_id, 'producer_facebook', true),
        'producer_whatsapp'      => get_user_meta($user_id, 'producer_whatsapp', true),
        'producer_website'       => get_user_meta($user_id, 'producer_website', true),
        'producer_tiktok'        => get_user_meta($user_id, 'producer_tiktok', true),
        'producer_youtube'       => get_user_meta($user_id, 'producer_youtube', true),
    ];
}

/* --- START: Main Form Rendering Function --- */
function tapin_render_producer_form($is_editing_mode = false) {
    if (!is_user_logged_in()) {
        echo '<div class="tapin-scope tapin-center-container"><div class="tapin-notice tapin-notice--error">יש להתחבר למערכת. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחברות</a></div></div>';
        return;
    }

    $u      = wp_get_current_user();
    $status = get_user_meta($u->ID, 'producer_status', true);
    $fields = tapin_producer_fields_defaults($u->ID);
    $msg    = '';

    $flash_key = 'tapin_bp_flash_'.get_current_user_id();
    if (get_transient($flash_key)) {
        $msg = get_transient($flash_key);
        delete_transient($flash_key);
    }

    $nonce_action = $is_editing_mode ? 'tapin_pr_submit' : 'tapin_bp_submit';
    $nonce_name   = $is_editing_mode ? 'tapin_pr_nonce'  : 'tapin_bp_nonce';

    /* --- FORM PROCESSING --- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$nonce_name]) && wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {

        if (!$is_editing_mode) {
            $unique = md5($u->ID . '|' . sanitize_text_field($_POST['producer_address'] ?? '') . '|' . sanitize_textarea_field($_POST['producer_about'] ?? ''));
            if (get_transient('tapin_bp_' . $unique)) {
                $msg = '<div class="tapin-notice tapin-notice--success">כבר התקבלה בקשה דומה. נסו שוב בעוד דקה.</div>';
            }
        }

        if (empty($msg)) {
            $display_name      = sanitize_text_field(wp_unslash($_POST['producer_display_name'] ?? ''));
            $about             = wp_kses_post(wp_unslash($_POST['producer_about'] ?? ''));
            $address           = sanitize_text_field(wp_unslash($_POST['producer_address'] ?? ''));
            $phone_pub_digits  = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_phone_public'] ?? '')));
            $phone_priv_digits = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_phone_private'] ?? '')));
            $instagram         = esc_url_raw(wp_unslash($_POST['producer_instagram'] ?? ''));
            $facebook          = esc_url_raw(wp_unslash($_POST['producer_facebook'] ?? ''));
            $whatsapp          = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_whatsapp'] ?? '')));
            $website           = esc_url_raw(wp_unslash($_POST['producer_website'] ?? ''));
            $tiktok            = esc_url_raw(wp_unslash($_POST['producer_tiktok'] ?? ''));
            $youtube           = esc_url_raw(wp_unslash($_POST['producer_youtube'] ?? ''));

            $has_cover_meta = (int) get_user_meta($u->ID, 'producer_cover_id', true) > 0 || get_user_meta($u->ID, 'cover_photo', true);
            $cover_in_post  = !empty($_FILES['producer_cover']['name']);
            $avatar_in_post = !empty($_FILES['producer_avatar']['name']);

            if (empty($display_name) || empty($about) || empty($address) || empty($phone_priv_digits)) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש למלא את כל שדות החובה (*).</div>';
            } elseif ( (!empty($phone_pub_digits) && strlen($phone_pub_digits) < 9) || strlen($phone_priv_digits) < 9 ) {
                $msg = '<div class="tapin-notice tapin-notice--error">מספרי טלפון חייבים להכיל 9 ספרות לפחות.</div>';
            } elseif (!$is_editing_mode && !$has_cover_meta && !$cover_in_post) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש להעלות תמונת קאבר.</div>';
            } else {

                require_once ABSPATH.'wp-admin/includes/file.php';
                require_once ABSPATH.'wp-admin/includes/media.php';
                require_once ABSPATH.'wp-admin/includes/image.php';

                /* COVER upload + UM sync */
                if ($cover_in_post && isset($_FILES['producer_cover']) && (int)$_FILES['producer_cover']['error'] === UPLOAD_ERR_OK) {
                    $max = wp_max_upload_size();
                    if (!empty($_FILES['producer_cover']['size']) && $_FILES['producer_cover']['size'] > $max) {
                        $msg = '<div class="tapin-notice tapin-notice--error">קובץ הקאבר גדול מהמותר (מקסימום '.size_format($max).').</div>';
                    } else {
                        $cov_id = media_handle_upload('producer_cover', 0);
                        if (is_wp_error($cov_id)) {
                            $msg = '<div class="tapin-notice tapin-notice--error">שגיאת קאבר: '.esc_html($cov_id->get_error_message()).'</div>';
                        } else {
                            update_user_meta($u->ID, 'producer_cover_id', (int)$cov_id);
                            $um_filename = tapin_um_sync_media_to_um_folder((int)$cov_id, $u->ID, 'cover');
                            if (!$um_filename) {
                                update_user_meta($u->ID, 'cover_photo', (int)$cov_id);
                                update_user_meta($u->ID, 'um_cover_photo', (int)$cov_id);
                            }
                        }
                    }
                }

                /* AVATAR upload + UM sync */
                if (empty($msg) && $avatar_in_post && isset($_FILES['producer_avatar']) && (int)$_FILES['producer_avatar']['error'] === UPLOAD_ERR_OK) {
                    $att_id = media_handle_upload('producer_avatar', 0);
                    if (is_wp_error($att_id)) {
                        $msg = '<div class="tapin-notice tapin-notice--error">שגיאת פרופיל: '.esc_html($att_id->get_error_message()).'</div>';
                    } else {
                        update_user_meta($u->ID, 'producer_avatar_id', (int)$att_id);
                        $um_filename = tapin_um_sync_media_to_um_folder((int)$att_id, $u->ID, 'profile');
                        if (!$um_filename) {
                            update_user_meta($u->ID, 'profile_photo', (int)$att_id);
                            update_user_meta($u->ID, 'um_profile_photo', (int)$att_id);
                        }
                    }
                }

                if (empty($msg)) {
                    wp_update_user([
                        'ID'           => $u->ID,
                        'display_name' => $display_name,
                        'first_name'   => $display_name,
                        'last_name'    => '',
                        'nickname'     => $display_name,
                        'user_url'     => $website
                    ]);

                    update_user_meta($u->ID, 'producer_about', $about);
                    update_user_meta($u->ID, 'producer_address', $address);
                    update_user_meta($u->ID, 'producer_phone_public', $phone_pub_digits);
                    update_user_meta($u->ID, 'producer_phone_private', $phone_priv_digits);
                    update_user_meta($u->ID, 'producer_instagram', $instagram);
                    update_user_meta($u->ID, 'producer_facebook', $facebook);
                    update_user_meta($u->ID, 'producer_whatsapp', $whatsapp);
                    update_user_meta($u->ID, 'producer_website', $website);
                    update_user_meta($u->ID, 'producer_tiktok', $tiktok);
                    update_user_meta($u->ID, 'producer_youtube', $youtube);

                    /* Mirrors for general usage */
                    update_user_meta($u->ID, 'instagram', $instagram);
                    update_user_meta($u->ID, 'facebook', $facebook);
                    update_user_meta($u->ID, 'tiktok', $tiktok);
                    update_user_meta($u->ID, 'youtube', $youtube);
                    update_user_meta($u->ID, 'whatsapp', $whatsapp);
                    update_user_meta($u->ID, 'phone_number', $phone_pub_digits);

                    /* Critical: sync into the exact UM field keys so they appear under the name */
                    tapin_sync_socials_to_um_keys($u->ID, [
                        'instagram' => $instagram,
                        'facebook'  => $facebook,
                        'tiktok'    => $tiktok,
                        'youtube'   => $youtube,
                        'whatsapp'  => $whatsapp,
                        'website'   => $website,
                    ]);

                    if (function_exists('UM')) {
                        UM()->user()->remove_cache($u->ID);
                    }

                    if ($is_editing_mode) {
                        $msg = '<div class="tapin-notice tapin-notice--success">הפרופיל עודכן.</div>';
                    } else {
                        update_user_meta($u->ID, 'producer_status', 'pending');
                        if (isset($unique)) set_transient('tapin_bp_' . $unique, 1, 60);
                        set_transient($flash_key, '<div class="tapin-notice tapin-notice--success">הבקשה נשלחה וממתינה לאישור מנהל.</div>', 60);
                        wp_safe_redirect(home_url());
                        exit;
                    }
                }
            }
        }
    }
    /* --- END FORM PROCESSING --- */

    $is_producer = in_array('producer', (array)$u->roles, true);
    $avatar_url  = tapin_get_um_profile_photo_url($u->ID, 'medium') ?: (function_exists('um_get_user_avatar_url') ? um_get_user_avatar_url('thumbnail', $u->ID) : get_avatar_url($u->ID, ['size'=>96]));
    $cover_url   = tapin_get_um_cover_url($u->ID, 'large');
    $max_txt     = size_format(wp_max_upload_size());

    ?>
    <div class="tapin-scope tapin-center-container" style="max-width: 860px;">
        <style>
            <?php echo tapin_get_shared_css_for_producer(); ?>
            .tapin-scope .tapin-avatar-preview{width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:2px solid var(--tapin-border-color)}
            .tapin-scope .tapin-cover-preview-wrap{border:2px dashed var(--tapin-border-color);border-radius:var(--tapin-radius-lg);padding:16px;display:grid;gap:16px;align-items:center;grid-template-columns:2fr 1fr}
            .tapin-scope .tapin-cover-preview{min-height:160px;background:var(--tapin-ghost-bg);border-radius:var(--tapin-radius-md);display:flex;align-items:center;justify-content:center;overflow:hidden}
            .tapin-scope .tapin-cover-preview img{width:100%;height:auto;display:block}
            @media (max-width:640px){.tapin-scope .tapin-cover-preview-wrap{grid-template-columns:1fr}}
        </style>

        <?php echo $msg; ?>

        <?php if ($is_editing_mode || $status === 'rejected' || empty($status)): ?>
            <form method="post" enctype="multipart/form-data" class="tapin-card" novalidate>
                <?php if ($status === 'rejected'): ?>
                    <div class="tapin-notice tapin-notice--error"><strong>סטטוס בקשה: נדחה.</strong><br>ניתן לעדכן את הפרטים ולשלוח את הבקשה מחדש.</div>
                <?php endif; ?>

                <div class="tapin-form-row">
                    <label for="p_display_name">שם במה (יוצג בפרופיל) <span style="color:#e11d48">*</span></label>
                    <input id="p_display_name" type="text" name="producer_display_name" value="<?php echo esc_attr($fields['producer_display_name']); ?>" required>
                </div>

                <div class="tapin-form-row">
                    <label>תמונת קאבר <?php if (!$is_editing_mode && !$cover_url) echo '<span style="color:#e11d48">*</span>'; ?> 
                        <small style="font-weight:normal;color:var(--tapin-text-light)">(מקסימום: <?php echo esc_html($max_txt); ?>)</small>
                    </label>
                    <div class="tapin-cover-preview-wrap">
                        <div class="tapin-cover-preview">
                            <?php if ($cover_url): ?><img src="<?php echo esc_url($cover_url); ?>" alt="תצוגה מקדימה של קאבר"><?php else: ?><span style="color:var(--tapin-text-light)"><?php echo $is_editing_mode ? 'אין קאבר עדיין' : 'תצוגה מקדימה'; ?></span><?php endif; ?>
                        </div>
                        <input type="file" name="producer_cover" accept="image/*" <?php if (!$is_editing_mode && !$cover_url) echo 'required'; ?>>
                    </div>
                </div>

                <div class="tapin-form-row">
                    <label>תמונת פרופיל</label>
                    <img src="<?php echo esc_url($avatar_url ?: get_avatar_url($u->ID, ['size'=>96])); ?>" alt="תצוגה מקדימה של אוואטר" class="tapin-avatar-preview">
                    <input type="file" name="producer_avatar" accept="image/*">
                </div>

                <div class="tapin-form-row">
                    <label for="p_address">כתובת <span style="color:#e11d48">*</span></label>
                    <input id="p_address" type="text" name="producer_address" value="<?php echo esc_attr($fields['producer_address']); ?>" required>
                </div>

                <div class="tapin-form-row">
                    <label for="p_phone_private">טלפון ליצירת קשר (פנימי) <span style="color:#e11d48">*</span></label>
                    <input id="p_phone_private" type="text" name="producer_phone_private" value="<?php echo esc_attr($fields['producer_phone_private']); ?>" placeholder="0500000000" inputmode="tel" required>
                </div>

                <div class="tapin-form-row">
                    <label for="p_about">אודות <span style="color:#e11d48">*</span></label>
                    <textarea id="p_about" name="producer_about" rows="6" required><?php echo esc_textarea($fields['producer_about']); ?></textarea>
                </div>

                <hr style="border:0; border-top:1px solid var(--tapin-border-color); margin: 24px 0;">
                <p style="text-align:center; color: var(--tapin-text-light); margin-top: -10px; margin-bottom: 20px;">שדות אופציונליים</p>

                <div class="tapin-columns-2">
                    <div class="tapin-form-row">
                        <label for="p_phone_public">טלפון לפרסום בדף</label>
                        <input id="p_phone_public" type="text" name="producer_phone_public" value="<?php echo esc_attr($fields['producer_phone_public']); ?>" placeholder="0500000000" inputmode="tel">
                    </div>
                    <div class="tapin-form-row">
                        <label for="p_whatsapp">ווצאפ</label>
                        <input id="p_whatsapp" type="text" name="producer_whatsapp" value="<?php echo esc_attr($fields['producer_whatsapp']); ?>" placeholder="0500000000" inputmode="tel">
                    </div>
                </div>

                <div class="tapin-columns-2">
                    <div class="tapin-form-row">
                        <label for="p_website">כתובת אתר</label>
                        <input id="p_website" type="url" name="producer_website" value="<?php echo esc_attr($fields['producer_website']); ?>" placeholder="https://example.com">
                    </div>
                    <div class="tapin-form-row">
                        <label for="p_instagram">אינסטגרם</label>
                        <input id="p_instagram" type="url" name="producer_instagram" value="<?php echo esc_attr($fields['producer_instagram']); ?>" placeholder="קישור מלא לפרופיל">
                    </div>
                </div>

                <div class="tapin-columns-2">
                    <div class="tapin-form-row">
                        <label for="p_facebook">פייסבוק</label>
                        <input id="p_facebook" type="url" name="producer_facebook" value="<?php echo esc_attr($fields['producer_facebook']); ?>" placeholder="קישור מלא לעמוד">
                    </div>
                    <div class="tapin-form-row">
                        <label for="p_tiktok">טיקטוק</label>
                        <input id="p_tiktok" type="url" name="producer_tiktok" value="<?php echo esc_attr($fields['producer_tiktok']); ?>" placeholder="קישור מלא לפרופיל">
                    </div>
                </div>

                <div class="tapin-form-row">
                    <label for="p_youtube">יוטיוב</label>
                    <input id="p_youtube" type="url" name="producer_youtube" value="<?php echo esc_attr($fields['producer_youtube']); ?>" placeholder="קישור מלא לערוץ">
                </div>

                <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                <div class="tapin-actions">
                    <button id="tapinSubmitBtn" type="submit" class="tapin-btn tapin-btn--primary">
                        <?php echo $is_editing_mode ? 'שמירת שינויים' : 'שליחת בקשה'; ?>
                    </button>
                </div>
            </form>

        <?php elseif ($is_producer): ?>
            <div class="tapin-card tapin-notice tapin-notice--success">הפרופיל שלך אושר ואתה מוגדר כמפיק.</div>

        <?php elseif ($status === 'pending'): ?>
            <div class="tapin-card tapin-notice tapin-notice--warning"><strong>סטטוס בקשה: ממתין לאישור מנהל.</strong><br>הבקשה שלך התקבלה ונמצאת בבדיקה.</div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
      var b=document.getElementById('tapinSubmitBtn'); if(b&&b.form){ b.form.addEventListener('submit', function(){ b.disabled=true; b.textContent='שולח…'; }); }
      ['producer_phone_public','producer_phone_private','producer_whatsapp'].forEach(function(name){
        document.querySelectorAll('input[name="'+name+'"]').forEach(function(el){
          el.addEventListener('input', function(){
            var v=this.value.replace(/\D+/g,'');
            if(this.value!==v) this.value=v;
          });
        });
      });
    })();
    </script>
    <?php
}
/* --- END: Main Form Rendering Function --- */

/* Endpoints renderers */
add_action('woocommerce_account_become-producer_endpoint', function () { tapin_render_producer_form(false); });
add_action('woocommerce_account_producer_endpoint', function () {
    if (!is_user_logged_in()) { echo '<div class="tapin-scope tapin-center-container"><div class="tapin-notice tapin-notice--error">יש להתחבר.</div></div>'; return; }
    if (!in_array('producer', (array)wp_get_current_user()->roles, true)) { echo '<div class="tapin-scope tapin-center-container"><div class="tapin-notice tapin-notice--error">אין לך הרשאת מפיק עדיין.</div></div>'; return; }
    tapin_render_producer_form(true);
});

/* Shortcode */
add_shortcode('producer_signup', function(){
    ob_start();
    echo '<div class="tapin-scope producer-signup-shortcode-wrapper">';
    tapin_render_producer_form(false);
    echo '</div>';
    return ob_get_clean();
});

/* Flash after redirect */
add_action('wp_footer', 'tapin_display_flash_message');
function tapin_display_flash_message() {
    if (!is_user_logged_in()) return;
    $flash_key = 'tapin_bp_flash_'.get_current_user_id();
    if (get_transient($flash_key)) {
        $message = get_transient($flash_key);
        delete_transient($flash_key);
        echo '<div id="tapin-flash-notice" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; padding: 12px 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); background: #f0fff4; border: 1px solid #b8e1c6; color: #065f46; direction: rtl;">'.$message.'</div>';
        echo "<script>setTimeout(function(){ var el = document.getElementById('tapin-flash-notice'); if(el) el.style.display='none'; }, 5000);</script>";
    }
}

/* --- START: Admin Request Manager --- */
add_shortcode('producer_requests_manager', function(){
    if (!is_user_logged_in() || !(current_user_can('manage_woocommerce') || in_array('owner', (array)wp_get_current_user()->roles, true))) {
        status_header(403);
        return '<div class="tapin-scope tapin-center-container"><div class="tapin-notice tapin-notice--error">הדף למנהלים בלבד.</div></div>';
    }

    $msg_html  = '';
    $flash_key = 'tapin_pm_flash_'.get_current_user_id();
    if (get_transient($flash_key)) {
        $payload = get_transient($flash_key);
        delete_transient($flash_key);
        if (is_array($payload) && !empty($payload['html'])) $msg_html = $payload['html'];
    }

    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tapin_pm_nonce']) && wp_verify_nonce($_POST['tapin_pm_nonce'], 'tapin_pm_action')) {
        $uid    = (int)$_POST['uid'];
        $action = sanitize_key($_POST['action_type'] ?? '');
        $user   = $uid ? get_user_by('id', $uid) : null;

        if ($user && in_array($action, ['approve','reject','remove'], true)) {
            $html = '';
            if ($action === 'approve') {
                $user->remove_role('customer');
                $user->add_role('producer');
                update_user_meta($uid, 'producer_status', 'approved');
                $html = '<div class="tapin-notice tapin-notice--success">הבקשה אושרה. המשתמש הוגדר כמפיק.</div>';
            } elseif ($action === 'reject') {
                $user->remove_role('producer');
                update_user_meta($uid, 'producer_status', 'rejected');
                $html = '<div class="tapin-notice tapin-notice--warning">הבקשה נדחתה.</div>';
            } else {
                $user->remove_role('producer');
                $user->add_role('customer');
                delete_user_meta($uid, 'producer_status');
                $html = '<div class="tapin-notice tapin-notice--warning">המפיק הוסר.</div>';
            }
            
            clean_user_cache($uid);
            if (function_exists('UM')) UM()->user()->remove_cache($uid);

            set_transient($flash_key, ['html'=>$html], 60);
            wp_safe_redirect(esc_url_raw(add_query_arg('pmf','1', get_permalink())));
            exit;
        }
    }

    $pending_users = get_users(['meta_key'=>'producer_status','meta_value'=>'pending','number'=>200]);
    $producers     = get_users(['role'=>'producer','orderby'=>'display_name','order'=>'ASC','number'=>999]);

    ob_start(); ?>
    <div class="tapin-scope tapin-center-container">
        <style>
            <?php echo tapin_get_shared_css_for_producer(); ?>
            .tapin-scope .tapin-manager-grid{display:grid;gap:16px}
            .tapin-scope .tapin-request-card__cover{height:140px;background:var(--tapin-ghost-bg);border-radius:var(--tapin-radius-md);overflow:hidden;margin-bottom:12px}
            .tapin-scope .tapin-request-card__cover img{width:100%;height:100%;object-fit:cover}
            .tapin-scope .tapin-request-card__header{display:flex;gap:12px;align-items:flex-start}
            .tapin-scope .tapin-request-card__avatar{width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--tapin-border-color)}
            .tapin-scope .tapin-request-card__name{font-weight:700;font-size:1.1rem;color:var(--tapin-text-dark)}
            .tapin-scope .tapin-request-card__meta{font-size:.9rem;color:var(--tapin-text-light);line-height:1.6}
            .tapin-scope .tapin-request-card__about{margin-top:10px;padding-top:10px;border-top:1px solid var(--tapin-border-color)}
            .tapin-scope .tapin-request-card__socials{margin-top:10px;padding-top:10px;border-top:1px solid var(--tapin-border-color);font-size:.9rem;line-height:1.7}
            .tapin-scope .tapin-producers-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--tapin-border-color);border-radius:var(--tapin-radius-lg);overflow:hidden}
            .tapin-scope .tapin-producers-table th,.tapin-scope .tapin-producers-table td{padding:12px 16px;border-bottom:1px solid var(--tapin-border-color);text-align:right;vertical-align:middle}
            .tapin-scope .tapin-producers-table thead th{background:var(--tapin-ghost-bg);font-weight:700}
            .tapin-scope .tapin-producers-table tbody tr:last-child td{border-bottom:0}
            .tapin-scope .tapin-producers-table td,.tapin-scope .tapin-producers-table td a{overflow-wrap:anywhere;word-break:break-word}
            .tapin-scope .tapin-producers-table .actions-cell{text-align:center;width:1%}
            @media(max-width:768px){
                .tapin-scope .tapin-producers-table thead{display:none}
                .tapin-scope .tapin-producers-table,
                .tapin-scope .tapin-producers-table tbody,
                .tapin-scope .tapin-producers-table tr,
                .tapin-scope .tapin-producers-table td{display:block}
                .tapin-scope .tapin-producers-table tr{border-bottom:2px solid var(--tapin-border-color);padding-bottom:10px;margin-bottom:10px}
                .tapin-scope .tapin-producers-table tr:last-child{border-bottom:0;margin-bottom:0}
                .tapin-scope .tapin-producers-table td{display:flex;justify-content:space-between;align-items:center;border:0;border-bottom:1px solid #f1f5f9;text-align:right;padding:10px 0}
                .tapin-scope .tapin-producers-table td::before{content:attr(data-label);font-weight:600;color:var(--tapin-text-dark)}
                .tapin-scope .tapin-producers-table td:last-child{border-bottom:0}
                .tapin-scope .tapin-producers-table .actions-cell{width:auto}
            }
        </style>

        <h3 class="tapin-title">בקשות מפיקים ממתינות</h3>
        <?php if (!empty($pending_users)): ?>
            <div class="tapin-manager-grid">
                <?php foreach ($pending_users as $user):
                    $uid    = $user->ID;
                    $fields = tapin_producer_fields_defaults($uid);
                    $avatar = tapin_get_um_profile_photo_url($uid, 'medium') ?: get_avatar_url($uid, ['size'=>96]);
                    $cover  = tapin_get_um_cover_url($uid, 'large');
                ?>
                <form method="post" class="tapin-card">
                    <?php if ($cover): ?><div class="tapin-request-card__cover"><img src="<?php echo esc_url($cover); ?>" alt="תמונת קאבר"></div><?php endif; ?>
                    <div class="tapin-request-card__header">
                        <img src="<?php echo esc_url($avatar); ?>" alt="תמונת פרופיל" class="tapin-request-card__avatar">
                        <div>
                            <div class="tapin-request-card__name"><?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
                            <div class="tapin-request-card__meta">
                                <strong>אימייל הרשמה:</strong> <?php echo esc_html($user->user_email); ?><br>
                                <?php if ($fields['producer_phone_public']): ?><strong>טלפון לפרסום:</strong> <a href="tel:<?php echo esc_attr($fields['producer_phone_public']); ?>"><?php echo esc_html($fields['producer_phone_public']); ?></a><br><?php endif; ?>
                                <?php if ($fields['producer_phone_private']): ?><strong>טלפון פנימי:</strong> <?php echo esc_html($fields['producer_phone_private']); ?><br><?php endif; ?>
                                <?php if ($fields['producer_address']): ?><strong>כתובת:</strong> <?php echo esc_html($fields['producer_address']); ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($fields['producer_about']): ?><div class="tapin-request-card__about"><?php echo wp_kses_post(wpautop($fields['producer_about'])); ?></div><?php endif; ?>
                    <div class="tapin-request-card__socials">
                        <?php if ($fields['producer_website']):   ?><strong>אתר:</strong>      <a href="<?php echo esc_url($fields['producer_website']);   ?>" target="_blank" rel="noopener"><?php echo esc_html($fields['producer_website']);   ?></a><br><?php endif; ?>
                        <?php if ($fields['producer_whatsapp']): ?><strong>ווצאפ:</strong>    <?php echo esc_html($fields['producer_whatsapp']); ?><br><?php endif; ?>
                        <?php if ($fields['producer_facebook']): ?><strong>פייסבוק:</strong>  <a href="<?php echo esc_url($fields['producer_facebook']); ?>" target="_blank" rel="noopener"><?php echo esc_html($fields['producer_facebook']); ?></a><br><?php endif; ?>
                        <?php if ($fields['producer_instagram']):?><strong>אינסטגרם:</strong> <a href="<?php echo esc_url($fields['producer_instagram']);?>" target="_blank" rel="noopener"><?php echo esc_html($fields['producer_instagram']);?></a><br><?php endif; ?>
                        <?php if ($fields['producer_tiktok']):   ?><strong>טיקטוק:</strong>   <a href="<?php echo esc_url($fields['producer_tiktok']);    ?>" target="_blank" rel="noopener"><?php echo esc_html($fields['producer_tiktok']);    ?></a><br><?php endif; ?>
                        <?php if ($fields['producer_youtube']):  ?><strong>יוטיוב:</strong>   <a href="<?php echo esc_url($fields['producer_youtube']);   ?>" target="_blank" rel="noopener"><?php echo esc_html($fields['producer_youtube']);   ?></a><br><?php endif; ?>
                    </div>
                    <div class="tapin-actions">
                        <button type="submit" name="action_type" value="approve" class="tapin-btn tapin-btn--primary">אישור</button>
                        <button type="submit" name="action_type" value="reject"  class="tapin-btn tapin-btn--danger" onclick="return confirm('לדחות בקשה זו?')">דחייה</button>
                    </div>
                    <?php wp_nonce_field('tapin_pm_action','tapin_pm_nonce'); ?>
                    <input type="hidden" name="uid" value="<?php echo (int)$uid; ?>">
                </form>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>אין בקשות ממתינות.</p>
        <?php endif; ?>

        <h3 class="tapin-title" style="margin-top: 40px;">כל המפיקים</h3>
        <div class="tapin-form-row">
            <input id="tapinProdSearch" type="text" placeholder="חיפוש לפי שם / מייל / טלפון...">
        </div>
        <table class="tapin-producers-table" id="tapinProdTable">
            <thead><tr><th>שם</th><th>אימייל</th><th>טלפון</th><th class="actions-cell">פעולות</th></tr></thead>
            <tbody id="tapinProdTBody">
                <?php if (!empty($producers)): foreach ($producers as $p):
                    $pid    = (int) $p->ID;
                    $pname  = $p->display_name ?: $p->user_login;
                    $pphone_pub  = get_user_meta($pid, 'producer_phone_public', true);
                    $pphone_priv = get_user_meta($pid, 'producer_phone_private', true);
                    $search_blob = strtolower($pname . ' ' . $p->user_email . ' ' . ($pphone_pub ?: '') . ' ' . ($pphone_priv ?: ''));
                ?>
                <tr data-name="<?php echo esc_attr($search_blob); ?>">
                    <td data-label="שם"><?php echo esc_html($pname); ?></td>
                    <td data-label="אימייל"><a href="mailto:<?php echo esc_attr($p->user_email); ?>"><?php echo esc_html($p->user_email); ?></a></td>
                    <td data-label="טלפון"><?php echo $pphone_pub ? '<a href="tel:'.esc_attr($pphone_pub).'">'.esc_html($pphone_pub).'</a>' : '—'; ?></td>
                    <td data-label="פעולות" class="actions-cell">
                        <form method="post" onsubmit="return confirm('להסיר את המפיק?');" style="margin:0">
                            <?php wp_nonce_field('tapin_pm_action','tapin_pm_nonce'); ?>
                            <input type="hidden" name="uid" value="<?php echo (int)$pid; ?>">
                            <input type="hidden" name="action_type" value="remove">
                            <button type="submit" class="tapin-btn tapin-btn--danger">הסר</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align:center;">אין מפיקים עדיין.</td></tr>
                <?php endif; ?>
                <tr id="tapinProdNoRes" style="display:none"><td colspan="4" style="text-align:center;">אין תוצאות</td></tr>
            </tbody>
        </table>
    </div>
    <script>
    (function(){
      var input = document.getElementById('tapinProdSearch'), tbody = document.getElementById('tapinProdTBody');
      if(!input || !tbody) return;
      var rows  = Array.from(tbody.querySelectorAll('tr[data-name]')), nores = document.getElementById('tapinProdNoRes');
      function filterRows() {
        var q = input.value.trim().toLowerCase(), visible = 0;
        rows.forEach(function(tr){
          var text = tr.dataset.name || '';
          if (!q || text.includes(q)) { tr.style.display = ''; visible++; } else { tr.style.display = 'none'; }
        });
        nores.style.display = visible ? 'none' : '';
      }
      input.addEventListener('input', filterRows);
    })();
    </script>
    <?php
    return ob_get_clean();
});
/* --- END: Admin Request Manager --- */

/* --- START: Ultimate Member Integrations --- */
add_action('um_profile_content_main_default', 'tapin_um_display_producer_about', 20);
function tapin_um_display_producer_about( $args ) {
    $user_id = !empty($args['user_id']) ? (int)$args['user_id'] : 0;
    if (!$user_id && function_exists('um_profile_id')) $user_id = um_profile_id();
    if (!$user_id) return;

    $about_text = get_user_meta($user_id, 'producer_about', true);
    if (!empty($about_text)) {
        echo '<div class="um-field" style="padding: 15px 0;">';
        echo '<div class="um-field-label" style="font-weight: bold; margin-bottom: 8px;"><label>אודות</label></div>';
        echo '<div class="um-field-value">' . wpautop(esc_html($about_text)) . '</div>';
        echo '</div>';
    }
}

add_filter('um_prepare_fields_for_profile', 'tapin_um_hide_producer_registration_email', 10, 2);
function tapin_um_hide_producer_registration_email($fields, $user_id) {
    if (isset($fields['user_email']) && user_can($user_id, 'producer')) {
        unset($fields['user_email']);
    }
    return $fields;
}

/* Avatar preference: UM meta (filename) -> attachment -> default */
add_filter('um_user_avatar_url_filter', function($url, $user_id){
    $meta = get_user_meta($user_id, 'profile_photo', true);
    if ($meta) {
        if (is_numeric($meta) && (int)$meta > 0) {
            $src = wp_get_attachment_image_src((int)$meta, 'medium');
            if (!$src) $src = wp_get_attachment_image_src((int)$meta, 'full');
            if ($src && !empty($src[0])) return $src[0];
        } else {
            $file_url = tapin_um_url_for_userfile($user_id, $meta);
            if ($file_url) return $file_url;
        }
    }
    $meta2 = get_user_meta($user_id, 'um_profile_photo', true);
    if (!empty($meta2) && !is_numeric($meta2)) {
        return tapin_um_url_for_userfile($user_id, $meta2);
    }
    $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
    if ($aid) {
        $src = wp_get_attachment_image_src($aid, 'medium');
        if (!$src) $src = wp_get_attachment_image_src($aid, 'full');
        if ($src && !empty($src[0])) return $src[0];
    }
    return $url;
}, 10, 2);

/* get_avatar(): same logic */
add_filter('pre_get_avatar_data', function ($args, $id_or_email) {
    $user_id = 0;
    if (is_numeric($id_or_email)) $user_id = (int)$id_or_email;
    elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) $user_id = (int)$id_or_email->user_id;
    elseif (is_string($id_or_email)) { $u = get_user_by('email', $id_or_email); if ($u) $user_id = $u->ID; }
    if ($user_id) {
        $meta = get_user_meta($user_id, 'profile_photo', true);
        if ($meta) {
            if (is_numeric($meta) && (int)$meta > 0) {
                $src = wp_get_attachment_image_src((int)$meta, 'thumbnail');
                if (!$src) $src = wp_get_attachment_image_src((int)$meta, 'full');
                if ($src && !empty($src[0])) $args['url'] = $src[0];
            } else {
                $args['url'] = tapin_um_url_for_userfile($user_id, $meta);
            }
        } else {
            $aid = (int) get_user_meta($user_id, 'producer_avatar_id', true);
            if ($aid) {
                $src = wp_get_attachment_image_src($aid, 'thumbnail');
                if (!$src) $src = wp_get_attachment_image_src($aid, 'full');
                if ($src && !empty($src[0])) $args['url'] = $src[0];
            }
        }
    }
    return $args;
}, 10, 2);

/* Inject cover image CSS on UM profile pages (prefers UM meta) */
add_action('wp_head', function () {
    if (!function_exists('um_is_core_page')) return;
    if (!(um_is_core_page('user') || um_is_core_page('profile') || um_is_core_page('account'))) return;

    $uid = function_exists('um_profile_id') ? um_profile_id() : get_current_user_id();
    if (!$uid) return;

    $cover_url = tapin_get_um_cover_url($uid, 'full');
    if (!$cover_url) return;

    $css = ".um-profile.um-viewing .um-cover-e{background-image:url(".esc_url($cover_url).")!important;background-size:cover!important;background-position:center!important}";
    echo '<style id="tapin-um-cover-css">' . $css . '</style>';
});
/* --- END: Ultimate Member Integrations --- */
