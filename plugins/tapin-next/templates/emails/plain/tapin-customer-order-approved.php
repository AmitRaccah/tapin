<?php
/**
 * Plain template for customer order approved email.
 *
 * @var WC_Order                                             $order
 * @var string                                               $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_CustomerOrderApproved $email
 */

defined('ABSPATH') || exit;

$headerBuffer = '';
ob_start();
do_action('woocommerce_email_header', $email_heading, $email);
$headerBuffer = trim(wp_strip_all_tags((string) ob_get_clean()));
if ($headerBuffer !== '') {
    echo $headerBuffer . "\n\n";
}

$site_name = trim((string) $email->get_blogname());
if ($site_name === '') {
    $site_name = get_bloginfo('name', 'display');
}
if ($site_name === '') {
    $site_name = get_bloginfo('name');
}
if ($site_name === '') {
    $site_name = 'Tapin';
}

$account_url = wc_get_page_permalink('myaccount');
if (empty($account_url)) {
    $account_url = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
}

$view_order_url = '';
if ($order instanceof WC_Order) {
    if (method_exists($order, 'get_view_order_url')) {
        $view_order_url = (string) $order->get_view_order_url();
    }

    if ($view_order_url === '') {
        $view_order_url = wc_get_endpoint_url('view-order', (string) $order->get_id(), $account_url);
    }
}
if ($view_order_url === '') {
    $view_order_url = $account_url;
}

$customer_name = '';
if ($order instanceof WC_Order) {
    $customer_name = trim((string) $order->get_formatted_billing_full_name());
    if ($customer_name === '') {
        $customer_name = trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
    }
}
if ($customer_name === '') {
    $customer_name = esc_html__('?????- Tapin', 'tapin');
}

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$order_total  = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';

$customer_name_plain   = trim(wp_strip_all_tags($customer_name));
$site_name_plain       = trim(wp_strip_all_tags($site_name));
$order_number_plain    = $order_number !== '' ? '#' . $order_number : '';
$event_name_plain      = trim(wp_strip_all_tags($event_name));
$event_date_plain      = trim(wp_strip_all_tags($event_date));
$event_location_plain  = trim(wp_strip_all_tags($event_location));

echo sprintf( esc_html__('?c????? %s,', 'tapin'), $customer_name_plain ) . "\n\n";
echo esc_html__('?"?????T? ???T?c?" ???x ?>?? ?"???c?x?x???T?? ?`?"?-???�?" ?c????.', 'tapin') . "\n\n";

if ($event_name_plain !== '' || $event_date_plain !== '' || $event_location_plain !== '') {
    if ($event_name_plain !== '') {
        echo sprintf( esc_html__('?c?? ?"???T?"??�: %s', 'tapin'), $event_name_plain ) . "\n";
    }
    if ($event_date_plain !== '') {
        echo sprintf( esc_html__('?x???"?T?? ??c?�?": %s', 'tapin'), $event_date_plain ) . "\n";
    }
    if ($event_location_plain !== '') {
        echo sprintf( esc_html__('???T????: %s', 'tapin'), $event_location_plain ) . "\n";
    }
    echo "\n";
}

if ($order_number_plain !== '') {
    echo sprintf( esc_html__('???�???" ?"?"?-???�?": %s', 'tapin'), $order_number_plain ) . "\n";
}
if ($order_total !== '') {
    echo sprintf( esc_html__('?�?>??? ?"?-?T??` ?"?�????T: %s', 'tapin'), $order_total ) . "\n";
}
echo "\n";

echo sprintf( esc_html__('???�???T?T?" ?`?"?-???�?" ?c????: %s', 'tapin'), esc_url_raw( $view_order_url ) ) . "\n\n";
echo sprintf( esc_html__('?x??"?" ?c?`?-?"?x ?`-%s', 'tapin'), $site_name_plain ) . "\n";
echo esc_html__('???-?>?T?? ???"????x ????x?? ?`???T?"??�. ??????? ???"?c?T?` ?????T?T?? ?-?" ??? ?????�??x ???�???x ?"?x???T?>?" ?c?? Tapin.', 'tapin') . "\n\n";

$additional = $email->get_additional_content();
if ($additional) {
    echo wp_strip_all_tags(wptexturize($additional)) . "\n\n";
}

$footerBuffer = '';
ob_start();
do_action('woocommerce_email_footer', $email);
$footerBuffer = trim(wp_strip_all_tags((string) ob_get_clean()));
if ($footerBuffer !== '') {
    echo $footerBuffer . "\n";
} else {
    echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'))) . "\n";
}

