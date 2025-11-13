<?php
/**
 * Plugin Name: Tapin Events (Next)
 * Version: 0.1.1
 */
if (!defined('ABSPATH')) exit;

define('TAPIN_EVENTS_NEXT_ACTIVE', true);
define('TAPIN_NEXT_PATH', __DIR__);
define('TAPIN_NEXT_NS',   'Tapin\\Events\\');

spl_autoload_register(function ($class) {
    $p = TAPIN_NEXT_NS;
    if (strpos($class, $p) !== 0) return;
    $rel  = str_replace('\\', '/', substr($class, strlen($p)));
    $file = TAPIN_NEXT_PATH . '/src/' . $rel . '.php';
    if (is_readable($file)) require_once $file;
});

if (!function_exists('tapin_next_debug_log')) {
    function tapin_next_debug_log(string $message): void
    {
        $shouldLog = defined('TAPIN_NEXT_DEBUG')
            ? (bool) TAPIN_NEXT_DEBUG
            : (defined('WP_DEBUG') && WP_DEBUG);

        if ($shouldLog) {
            error_log('[Tapin Next] ' . $message);
        }
    }
}

function tapin_next_is_sandbox(): bool {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return false;
    return !empty($_COOKIE['tapin_sandbox']);
}

function tapin_next_toggle_sandbox_cookie(bool $enabled): void {
    $ttl = 15 * MINUTE_IN_SECONDS;
    $expires = $enabled ? time() + $ttl : time() - 3600;
    $path = defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    $secure = is_ssl();
    $httponly = true;
    $value = $enabled ? '1' : '';

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        setcookie('tapin_sandbox', $value, [
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('tapin_sandbox', $value, $expires, $path, $domain, $secure, $httponly);
    }

    if ($enabled) {
        $_COOKIE['tapin_sandbox'] = '1';
    } else {
        unset($_COOKIE['tapin_sandbox']);
    }
}

function tapin_next_handle_sandbox_toggle(): void {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(__('אין הרשאה לביצוע הפעולה.', 'tapin'), 'tapin_sandbox_forbidden', ['response' => 403]);
    }

    check_admin_referer('tapin_toggle_sandbox');

    $requested = isset($_REQUEST['tapin_sandbox']) ? (string) wp_unslash((string) $_REQUEST['tapin_sandbox']) : '0';
    $enabled = $requested !== '0';

    tapin_next_toggle_sandbox_cookie($enabled);

    $userId = get_current_user_id();
    do_action('tapin/sandbox/toggled', $enabled, $userId);
    tapin_next_debug_log('[sandbox] toggled ' . ($enabled ? 'on' : 'off') . " by user {$userId}");

    $redirectParam = isset($_REQUEST['redirect_to']) ? wp_sanitize_redirect((string) wp_unslash((string) $_REQUEST['redirect_to'])) : '';
    $target = $redirectParam !== '' ? $redirectParam : wp_get_referer();
    if (!$target) {
        $target = admin_url();
    }

    wp_safe_redirect($target);
    exit;
}
add_action('admin_post_tapin_toggle_sandbox', 'tapin_next_handle_sandbox_toggle');

/**
 * @deprecated 0.1.2 Support the signed admin-post toggle instead.
 */
function tapin_next_handle_legacy_sandbox_toggle(): void {
    if (!isset($_GET['tapin_sandbox']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    if (!wp_verify_nonce((string) wp_unslash((string) $_GET['_wpnonce']), 'tapin_toggle_sandbox')) {
        return;
    }

    if (function_exists('_deprecated_function')) {
        _deprecated_function(__FUNCTION__, 'tapin-next 0.1.2', 'tapin_next_sandbox_toggle_url');
    }

    $enabled = (string) wp_unslash((string) $_GET['tapin_sandbox']) !== '0';
    tapin_next_toggle_sandbox_cookie($enabled);
    $userId = get_current_user_id();
    do_action('tapin/sandbox/toggled', $enabled, $userId);
    tapin_next_debug_log('[sandbox] legacy toggle ' . ($enabled ? 'on' : 'off') . " by user {$userId}");

    $target = wp_get_referer();
    if (!$target && !empty($_SERVER['REQUEST_URI'])) {
        $target = home_url(remove_query_arg(['tapin_sandbox', '_wpnonce'], wp_unslash((string) $_SERVER['REQUEST_URI'])));
    } elseif ($target) {
        $target = remove_query_arg(['tapin_sandbox', '_wpnonce'], $target);
    }

    if (!$target) {
        $target = admin_url();
    }

    wp_safe_redirect($target);
    exit;
}
add_action('init', 'tapin_next_handle_legacy_sandbox_toggle');

function tapin_next_sandbox_toggle_url(bool $enableSandbox, ?string $redirect = null): string {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '';
    }

    $args = [
        'action'        => 'tapin_toggle_sandbox',
        'tapin_sandbox' => $enableSandbox ? '1' : '0',
    ];

    if ($redirect !== null && $redirect !== '') {
        $args['redirect_to'] = $redirect;
    }

    $url = add_query_arg($args, admin_url('admin-post.php'));
    return wp_nonce_url($url, 'tapin_toggle_sandbox');
}

add_action('plugins_loaded', function () {
    if (!class_exists(\Tapin\Events\Core\Plugin::class)) return;
    (new \Tapin\Events\Core\Plugin())->boot(['sandbox' => tapin_next_is_sandbox()]);
});
