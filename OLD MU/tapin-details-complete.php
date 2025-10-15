<?php

const UM_COMPLETE_PAGE_ID = 575; 

function tapin_umc_edit_url(): string {
    return add_query_arg(['um_action' => 'edit'], get_permalink(UM_COMPLETE_PAGE_ID));
}


function tapin_umc_should_require_last_name(int $uid): bool {
    $u = get_userdata($uid);
    if (!$u) return true; 
    $roles = (array) $u->roles;
    if (in_array('producer', $roles, true)) return false;
    $status = get_user_meta($uid, 'producer_status', true);
    if (in_array($status, ['pending','approved','rejected'], true)) return false;
    return true;
}

function tapin_umc_required_fields(int $uid): array {
    $req = [
        'first_name',
        'gender',
        'birth_date',
        
        ['whatsapp','billing_phone','phone_number','phone','mobile','mobile_number'],
    ];
    if (tapin_umc_should_require_last_name($uid)) {
        $req[] = 'last_name';
    }
    return $req;
}


function tapin_umc_get_missing_fields(int $uid): array {
    $required = tapin_umc_required_fields($uid);
    $missing = [];
    foreach ($required as $item) {
        $keys = is_array($item) ? $item : [$item];
        $ok = false;
        foreach ($keys as $key) {
            $v = get_user_meta($uid, $key, true);
            if (is_array($v)) $v = trim(implode(',', array_filter($v)));
            else             $v = trim((string)$v);
            if ($v !== '') { $ok = true; break; }
        }
        if (!$ok) $missing[] = is_array($item) ? $keys[0] : $item;
    }
    return $missing;
}

add_action('user_register', function (int $user_id) {
    update_user_meta($user_id, 'must_complete_profile', 1);
}, 10);

add_action('woocommerce_created_customer', function (int $user_id) {
    update_user_meta($user_id, 'must_complete_profile', 1);
}, 10);

add_action('nsl_google_register_new_user', function (int $user_id) {
    update_user_meta($user_id, 'must_complete_profile', 1);
    update_user_meta($user_id, 'first_name', '');
    update_user_meta($user_id, 'last_name',  '');
}, 10);


add_filter('login_redirect', function($redirect_to, $request, $user){
    if (!is_a($user, 'WP_User')) return $redirect_to;
    $missing = tapin_umc_get_missing_fields($user->ID);
    return !empty($missing) ? tapin_umc_edit_url() : $redirect_to;
}, 10, 3);


add_filter('google_register_redirect_url', function ($url) {
    return tapin_umc_edit_url();
}, 10);

add_action('template_redirect', function () {
    if (!is_user_logged_in() || is_admin() || wp_doing_ajax()) return;

    $uid     = get_current_user_id();
    $missing = tapin_umc_get_missing_fields($uid);

    if (isset($_GET['umcdebug']) && current_user_can('manage_options')) {
        wp_die(
            'UM Complete – missing: <b>'.implode(', ', $missing).'</b><br>' .
            'first_name='.esc_html(get_user_meta($uid,'first_name',true)).'<br>' .
            'last_name=' .esc_html(get_user_meta($uid,'last_name',true)).'<br>' .
            'gender='    .esc_html(get_user_meta($uid,'gender',true)).'<br>' .
            'birth_date='.esc_html(get_user_meta($uid,'birth_date',true)).'<br>' .
            'whatsapp='  .esc_html(get_user_meta($uid,'whatsapp',true)).'<br>'
        );
    }

    if (empty($missing)) {
        delete_user_meta($uid, 'must_complete_profile'); // שחרור
        return;
    }

    $on_edit_page = (get_queried_object_id() === UM_COMPLETE_PAGE_ID)
                    && (isset($_GET['um_action']) && $_GET['um_action'] === 'edit');

    if (!$on_edit_page) {
        wp_safe_redirect(tapin_umc_edit_url());
        exit;
    }
});

function tapin_um_set_default_status($user_id){
    $status = get_user_meta($user_id, 'account_status', true);
    if (empty($status)) update_user_meta($user_id, 'account_status', 'approved');
}
add_action('user_register', 'tapin_um_set_default_status', 5);
add_action('nsl_google_register_new_user', 'tapin_um_set_default_status', 5, 1);
add_action('wp_login', function($login, $user){ tapin_um_set_default_status($user->ID); }, 10, 2);
