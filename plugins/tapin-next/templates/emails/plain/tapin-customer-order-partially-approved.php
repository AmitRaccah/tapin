<?php

defined('ABSPATH') || exit;

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

$customer_name = '';
if ($order instanceof WC_Order) {
    $customer_name = trim((string) $order->get_formatted_billing_full_name());
    if ($customer_name === '') {
        $customer_name = trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
    }
}
if ($customer_name === '') {
    $customer_name = esc_html__('Tapin customer', 'tapin');
}

$order_number  = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$order_total   = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';
$partial_raw   = $order instanceof WC_Order ? (float) $order->get_meta('_tapin_partial_approved_total', true) : 0.0;
$partial_label = '';
if ($order instanceof WC_Order && $partial_raw > 0 && function_exists('wc_price')) {
    $partial_label = wp_strip_all_tags(wc_price($partial_raw, ['currency' => $order->get_currency()]));
}

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$producer_id = isset($producer_id) ? (int) $producer_id : 0;

$approved_attendees = [];
$pending_attendees  = [];

if ($order instanceof WC_Order && $producer_id > 0 && class_exists('\Tapin\Events\Features\Orders\ProducerApprovals\OrderSummaryBuilder')) {
    $builder = new \Tapin\Events\Features\Orders\ProducerApprovals\OrderSummaryBuilder();
    $summary = $builder->buildOrderSummary($order, $producer_id);

    $list = [];
    if (!empty($summary['primary_attendee']) && is_array($summary['primary_attendee'])) {
        $list[] = (array) $summary['primary_attendee'];
    }
    foreach ((array) ($summary['attendees'] ?? []) as $attendee) {
        if (is_array($attendee)) {
            $list[] = $attendee;
        }
    }

    foreach ($list as $attendee) {
        $name = trim((string) ($attendee['full_name'] ?? ''));
        if ($name === '') {
            $first = trim((string) ($attendee['first_name'] ?? ''));
            $last  = trim((string) ($attendee['last_name'] ?? ''));
            $name  = trim($first . ' ' . $last);
        }
        if ($name === '') {
            $name = esc_html__('Attendee', 'tapin');
        }

        $label = (string) ($attendee['ticket_type_label'] ?? ($attendee['ticket_type'] ?? ''));

        $row = [
            'name'  => $name,
            'label' => $label,
        ];

        $isApproved = !empty($attendee['is_producer_approved']);
        if ($isApproved) {
            $approved_attendees[] = $row;
        } else {
            $pending_attendees[]  = $row;
        }
    }
}

echo '=' . $email_heading . "=\n\n";

printf(
    esc_html__('Hi %1$s, part of your order at %2$s has been approved.', 'tapin'),
    $customer_name,
    $site_name
);
echo "\n\n";

if ($event_name !== '' || $event_date !== '' || $event_location !== '') {
    if ($event_name !== '') {
        printf(esc_html__('Event: %s', 'tapin'), $event_name);
        echo "\n";
    }
    if ($event_date !== '') {
        printf(esc_html__('Date and time: %s', 'tapin'), $event_date);
        echo "\n";
    }
    if ($event_location !== '') {
        printf(esc_html__('Location: %s', 'tapin'), $event_location);
        echo "\n";
    }
    echo "\n";
}

if ($order_number !== '') {
    printf(esc_html__('Order number: #%s', 'tapin'), $order_number);
    echo "\n";
}

if ($partial_label !== '') {
    printf(esc_html__('Approved amount so far: %s', 'tapin'), $partial_label);
    echo "\n";
} elseif ($order_total !== '') {
    printf(esc_html__('Order total: %s', 'tapin'), $order_total);
    echo "\n";
}

echo "\n";

if ($approved_attendees !== []) {
    echo esc_html__("Approved attendees:", 'tapin') . "\n";
    foreach ($approved_attendees as $row) {
        $line = '- ' . $row['name'];
        if ($row['label'] !== '') {
            $line .= ' (' . $row['label'] . ')';
        }
        echo $line . "\n";
    }
    echo "\n";
}

if ($pending_attendees !== []) {
    echo esc_html__("Waiting for approval:", 'tapin') . "\n";
    foreach ($pending_attendees as $row) {
        $line = '- ' . $row['name'];
        if ($row['label'] !== '') {
            $line .= ' (' . $row['label'] . ')';
        }
        echo $line . "\n";
    }
    echo "\n";
}

if ($additional_content = $email->get_additional_content()) {
    echo wp_strip_all_tags(wptexturize($additional_content)) . "\n\n";
}

echo esc_html__('If you have questions about this order, please contact Tapin support.', 'tapin') . "\n";
echo "support@tapin.co.il\n";
