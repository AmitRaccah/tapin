<?php

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);

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

$site_url = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
$apply_canonical = static function (string $url) use ($site_url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $baseParts = wp_parse_url($site_url);
    if (!is_array($baseParts) || empty($baseParts['host'])) {
        return $url;
    }

    $parts    = wp_parse_url($url);
    $path     = is_array($parts) && isset($parts['path']) ? $parts['path'] : '/' . ltrim($url, '/');
    $query    = is_array($parts) && !empty($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = is_array($parts) && !empty($parts['fragment']) ? '#' . $parts['fragment'] : '';

    $scheme = $baseParts['scheme'] ?? 'https';
    $host   = $baseParts['host'];
    $port   = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

    return $scheme . '://' . $host . $port . $path . $query . $fragment;
};

$account_url = $apply_canonical((string) wc_get_page_permalink('myaccount'));
if ($account_url === '') {
    $account_url = $site_url;
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
$view_order_url = $apply_canonical($view_order_url);
if ($view_order_url === '') {
    $view_order_url = $account_url;
}

$ticket_url = isset($ticket_url) ? $apply_canonical((string) $ticket_url) : '';
if ($ticket_url === '' && $view_order_url !== '') {
    $ticket_url = $view_order_url;
}

$qr_image_url = isset($qr_image_url) ? $apply_canonical((string) $qr_image_url) : '';

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

$event_context  = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name     = trim((string) ($event_context['event_name'] ?? ''));
$event_date     = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address  = trim((string) ($event_context['event_address'] ?? ''));
$event_city     = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

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
            $name = esc_html__('שם משתתף חסר', 'tapin');
        }

        $label = (string) ($attendee['ticket_type_label'] ?? ($attendee['ticket_type'] ?? ''));

        $approved_attendees[] = [
            'name'  => $name,
            'label' => $label,
        ];
    }
}

$additional_content = $email->get_additional_content();

$preheader_text = sprintf(
    esc_html__('ההזמנה שלך ב-%s אושרה.', 'tapin'),
    esc_html($site_name)
);

ob_start();
?>
<td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
    <?php esc_html_e('ההזמנה שלך אושרה ב', 'tapin'); ?>
    <span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
</td>
<?php
$header_html = trim((string) ob_get_clean());

ob_start();
?>
<td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
    <?php esc_html_e('שלום', 'tapin'); ?>
    <strong><?php echo esc_html($customer_name); ?></strong>,<br />
    <?php esc_html_e('תודה! ההזמנה שלך אושרה על ידי המפיק.', 'tapin'); ?><br />
    <?php esc_html_e('ריכזנו עבורך את פרטי האירוע וההזמנה. הכרטיסים למשתתפים יישלחו לאחר האישור.', 'tapin'); ?><br />
    <?php if ($approved_attendees !== []) : ?>
        <br />
        <strong><?php esc_html_e('משתתפים שאושרו:', 'tapin'); ?></strong><br />
        <?php foreach ($approved_attendees as $row) : ?>
            &ndash; <?php echo esc_html($row['name']); ?>
            <?php if ($row['label'] !== '') : ?>
                (<?php echo esc_html($row['label']); ?>)
            <?php endif; ?>
            <br />
        <?php endforeach; ?>
    <?php endif; ?>
</td>
<?php
$body_html = trim((string) ob_get_clean());

$button_html = '';
if ($qr_image_url !== '' || $ticket_url !== '' || $view_order_url !== '') {
    $button_label = esc_html__('פתיחת תמונת ה-QR', 'tapin');
    $button_url   = $qr_image_url !== '' ? $qr_image_url : ($ticket_url !== '' ? $ticket_url : $view_order_url);
    ob_start();
    ?>
    <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($button_url); ?>">
        <?php echo esc_html($button_label); ?>
    </a>
    <?php
    $button_html = trim((string) ob_get_clean());
}

$meta_rows_html = '';
if ($event_name !== '' || $event_date !== '' || $event_location !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <?php if ($event_name !== '') : ?>
                <strong><?php esc_html_e('שם האירוע:', 'tapin'); ?></strong>
                <?php echo esc_html($event_name); ?><br />
            <?php endif; ?>
            <?php if ($event_date !== '') : ?>
                <strong><?php esc_html_e('תאריך האירוע:', 'tapin'); ?></strong>
                <?php echo esc_html($event_date); ?><br />
            <?php endif; ?>
            <?php if ($event_location !== '') : ?>
                <strong><?php esc_html_e('מיקום:', 'tapin'); ?></strong>
                <?php echo esc_html($event_location); ?><br />
            <?php endif; ?>
        </td>
    </tr>
    <?php
    $meta_rows_html .= trim((string) ob_get_clean());
}
if ($order_number !== '' || $order_total !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <?php if ($order_number !== '') : ?>
                <strong><?php esc_html_e('מספר הזמנה:', 'tapin'); ?></strong>
                <?php echo esc_html('#' . $order_number); ?><br />
            <?php endif; ?>
            <?php if ($order_total !== '') : ?>
                <strong><?php esc_html_e('סכום ששולם:', 'tapin'); ?></strong>
                <?php echo wp_kses_post($order_total); ?><br />
            <?php endif; ?>
        </td>
    </tr>
    <?php
    $meta_rows_html .= trim((string) ob_get_clean());
}
$qr_fallback_html = '';
if (empty($qr_image_url) && $ticket_url !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <strong><?php esc_html_e('לא הצלחנו לצרף תמונת QR.', 'tapin'); ?></strong><br />
            <?php esc_html_e('אפשר לפתוח את הכרטיס בקישור הבטוח הבא:', 'tapin'); ?><br />
            <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($ticket_url); ?>"><?php echo esc_html($ticket_url); ?></a>
        </td>
    </tr>
    <?php
    $qr_fallback_html = trim((string) ob_get_clean());
}

ob_start();
?>
<td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
    <?php if ($additional_content) : ?>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        <br /><br />
    <?php endif; ?>
    <?php esc_html_e('אם יש שינוי או שאלה, אנחנו זמינים בכתובת:', 'tapin'); ?>
    <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
</td>
<?php
$additional_html = trim((string) ob_get_clean());

ob_start();
?>
<?php
printf(
    esc_html__('הודעה זו נשלחה אוטומטית על ידי %s.', 'tapin'),
    esc_html($site_name)
);
?>
<br />
<?php esc_html_e('תודה שבחרתם בטאפין.', 'tapin'); ?>
<br />
<?php esc_html_e('ביקור באתר:', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_url); ?></a>
<?php
$footer_html = trim((string) ob_get_clean());

wc_get_template(
    'emails/partials/tapin-dark-wrapper.php',
    [
        'preheader_text'  => $preheader_text,
        'header_html'     => $header_html,
        'body_html'       => $body_html,
        'qr_image_html'   => '',
        'button_html'     => $button_html,
        'meta_rows_html'  => $meta_rows_html . $qr_fallback_html,
        'additional_html' => $additional_html,
        'footer_html'     => $footer_html,
    ],
    '',
    trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
);

do_action('woocommerce_email_footer', $email);
