<?php
/**
 * Plain text producer email for approved order.
 *
 * @var WC_Order                                                       $order
 * @var string                                                         $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_ProducerOrderApproved $email
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
$items_count  = $order instanceof WC_Order ? $order->get_item_count() : 0;

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$lines   = [];
$lines[] = sprintf(esc_html__('Order #%1$s from %2$s has been approved.', 'tapin'), $order_number, $site_name);
if ($customer_name !== '') {
    $lines[] = sprintf(esc_html__('Customer: %s', 'tapin'), $customer_name);
}
if ($items_count > 0) {
    $lines[] = sprintf(esc_html__('Items: %d', 'tapin'), $items_count);
}
if ($total !== '') {
    $lines[] = sprintf(esc_html__('Order total: %s', 'tapin'), $total);
}
$eventHasData = $event_name !== '' || $event_date !== '' || $event_location !== '';
if ($eventHasData) {
    if ($event_name !== '') {
        $lines[] = sprintf(esc_html__('Event: %s', 'tapin'), $event_name);
    }
    if ($event_date !== '') {
        $lines[] = sprintf(esc_html__('Date and time: %s', 'tapin'), $event_date);
    }
    if ($event_location !== '') {
        $lines[] = sprintf(esc_html__('Location: %s', 'tapin'), $event_location);
    }
}
$lines[] = sprintf(esc_html__('Manage this order: %s', 'tapin'), $dashboard_url);

echo implode("\n", array_filter($lines));
