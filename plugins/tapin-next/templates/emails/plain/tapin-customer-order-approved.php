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
    $customer_name = esc_html__('לקוח Tapin', 'tapin');
}

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$order_total  = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';

$producer_id = isset($producer_id) ? (int) $producer_id : 0;

$approved_attendees = [];

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
            $name = esc_html__('משתתף ללא שם', 'tapin');
        }

        $label = (string) ($attendee['ticket_type_label'] ?? ($attendee['ticket_type'] ?? ''));

        $approved_attendees[] = [
            'name'  => $name,
            'label' => $label,
        ];
    }
}

echo '=' . $email_heading . "=\n\n";

printf(
    esc_html__('שלום %1$s, כל המשתתפים בהזמנה שלך באתר %2$s אושרו.', 'tapin'),
    $customer_name,
    $site_name
);
echo "\n\n";

if ($order_number !== '') {
    printf(esc_html__('מספר ההזמנה: #%s', 'tapin'), $order_number);
    echo "\n";
}

if ($order_total !== '') {
    printf(esc_html__('סכום החיוב הסופי: %s', 'tapin'), $order_total);
    echo "\n";
}

echo "\n";

if ($approved_attendees !== []) {
    echo esc_html__('משתתפים שאושרו:', 'tapin') . "\n";
    foreach ($approved_attendees as $row) {
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

echo esc_html__('מחכים לראותך באירוע. עבור כל שאלה, ניתן להשיב למייל זה או לפנות לצוות התמיכה של Tapin.', 'tapin') . "\n";
echo "support@tapin.co.il\n";
