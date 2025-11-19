<?php
/**
 * Plain text version of producer awaiting-approval email.
 *
 * @var WC_Order                                                          $order
 * @var string                                                            $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_ProducerAwaitingApproval $email
 */

defined('ABSPATH') || exit;

$site_name = trim((string) $email->get_blogname());
if ($site_name === '') {
    $site_name = get_bloginfo('name', 'display');
}
if ($site_name === '') {
    $site_name = 'Tapin';
}

$dashboard_url = '';
if (function_exists('get_posts')) {
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'numberposts'    => 1,
        's'              => '[producer_order_approvals',
        'suppress_filters' => false,
    ]);
    if (!empty($pages) && isset($pages[0])) {
        $dashboard_url = get_permalink($pages[0]);
    }
}
if ($dashboard_url === '') {
    $dashboard_url = admin_url('edit.php?post_type=shop_order');
}

$customer_name = '';
if ($order instanceof WC_Order) {
    $customer_name = trim((string) $order->get_formatted_billing_full_name());
    if ($customer_name === '') {
        $customer_name = trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
    }
}

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$total        = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';

$lines   = [];
$lines[] = sprintf(esc_html__( 'הזמנה #%1$s באתר %2$s ממתינה לאישור שלך.', 'tapin' ), $order_number, $site_name);
if ($customer_name !== '') {
    $lines[] = sprintf(esc_html__( 'שם הלקוח/ה: %s', 'tapin' ), $customer_name);
}
if ($total !== '') {
    $lines[] = sprintf(esc_html__( 'סכום העסקה: %s', 'tapin' ), $total);
}
$lines[] = sprintf(esc_html__( 'לצפייה בהזמנה: %s', 'tapin' ), $dashboard_url);

echo implode("\n", array_filter($lines));

