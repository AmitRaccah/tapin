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

$site_url    = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
$account_url = wc_get_page_permalink('myaccount');
if (empty($account_url)) {
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
    $customer_name = esc_html__('לקוח Tapin', 'tapin');
}

$order_number   = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$order_total    = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';
$partial_raw    = $order instanceof WC_Order ? (float) $order->get_meta('_tapin_partial_approved_total', true) : 0.0;
$partial_total  = '';
if ($order instanceof WC_Order && $partial_raw > 0 && function_exists('wc_price')) {
    $partial_total = wc_price($partial_raw, ['currency' => $order->get_currency()]);
}

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
            $name = esc_html__('משתתף ללא שם', 'tapin');
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
            $pending_attendees[] = $row;
        }
    }
}

$additional_content = $email->get_additional_content();

ob_start();
printf(
    esc_html__('חלק מהמשתתפים בהזמנה שלך באתר %s אושרו, וחלק עדיין ממתינים או לא אושרו.', 'tapin'),
    esc_html($site_name)
);
$preheader_text = trim((string) ob_get_clean());

ob_start();
?>
<td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
    <?php esc_html_e('אישור חלקי של הזמנה ב', 'tapin'); ?>
    <span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
</td>
<?php
$header_html = trim((string) ob_get_clean());

ob_start();
?>
<td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
    <?php esc_html_e('שלום', 'tapin'); ?>
    <strong><?php echo esc_html($customer_name); ?></strong>,<br />
    <?php esc_html_e('המפיק עבר על ההזמנה שלך ואישר חלק מהמשתתפים.', 'tapin'); ?><br />
    <?php esc_html_e('להלן פירוט המשתתפים שאושרו ומי שלא אושר בשלב זה.', 'tapin'); ?><br />
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
    <?php if ($pending_attendees !== []) : ?>
        <br />
        <strong><?php esc_html_e('משתתפים שלא אושרו בשלב זה:', 'tapin'); ?></strong><br />
        <?php foreach ($pending_attendees as $row) : ?>
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
if ($view_order_url !== '') {
    $button_label = esc_html__('לצפייה בהזמנה המלאה', 'tapin');
    ob_start();
    ?>
    <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($view_order_url); ?>">
        <?php echo esc_html($button_label); ?>
    </a>
    <?php
    $button_html = trim((string) ob_get_clean());
}

$meta_rows_html = '';
if ($order_number !== '' || $partial_total !== '' || $order_total !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <?php if ($order_number !== '') : ?>
                <strong><?php esc_html_e('מספר ההזמנה:', 'tapin'); ?></strong>
                <?php echo esc_html('#' . $order_number); ?><br />
            <?php endif; ?>
            <?php if ($partial_total !== '') : ?>
                <strong><?php esc_html_e('סכום משוער לחיוב עבור המשתתפים שאושרו:', 'tapin'); ?></strong>
                <?php echo wp_kses_post($partial_total); ?><br />
            <?php elseif ($order_total !== '') : ?>
                <strong><?php esc_html_e('סכום ההזמנה:', 'tapin'); ?></strong>
                <?php echo wp_kses_post($order_total); ?><br />
            <?php endif; ?>
        </td>
    </tr>
    <?php
    $meta_rows_html = trim((string) ob_get_clean());
}

ob_start();
?>
<td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
    <?php if ($additional_content) : ?>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        <br /><br />
    <?php endif; ?>
    <?php esc_html_e('אם יש לך שאלות לגבי האישור החלקי, ניתן להשיב למייל זה או לפנות לצוות התמיכה של Tapin.', 'tapin'); ?>
    <br />
    <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
</td>
<?php
$additional_html = trim((string) ob_get_clean());

ob_start();
?>
<?php
printf(
    esc_html__('הודעה זו נשלחה באופן אוטומטי בעקבות עדכון סטטוס ההזמנה שלך באתר %s.', 'tapin'),
    esc_html($site_name)
);
?>
<br />
<?php esc_html_e('הצוות של Tapin תמיד כאן בשבילך.', 'tapin'); ?>
<br />
<?php esc_html_e('לאתר שלנו:', 'tapin'); ?>
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
        'meta_rows_html'  => $meta_rows_html,
        'additional_html' => $additional_html,
        'footer_html'     => $footer_html,
    ],
    '',
    trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
);

do_action('woocommerce_email_footer', $email);
