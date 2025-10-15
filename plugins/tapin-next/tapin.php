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

function tapin_next_is_sandbox(): bool {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return false;
    return !empty($_COOKIE['tapin_sandbox']) || isset($_GET['tapin_sandbox']);
}

add_action('init', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;
    if (!isset($_GET['tapin_sandbox'])) return;
    $ttl = 15 * MINUTE_IN_SECONDS;
    if ((string)$_GET['tapin_sandbox'] === '0') {
        setcookie('tapin_sandbox', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        unset($_COOKIE['tapin_sandbox']);
    } else {
        setcookie('tapin_sandbox', '1', time() + $ttl, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['tapin_sandbox'] = '1';
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists(\Tapin\Events\Core\Plugin::class)) return;
    (new \Tapin\Events\Core\Plugin())->boot(['sandbox' => tapin_next_is_sandbox()]);
});
