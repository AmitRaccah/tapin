<?php
/**
 * Producer email for order approved by producer.
 *
 * @var WC_Order                                                         $order
 * @var string                                                           $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_ProducerOrderApproved   $email
 */

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

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$total        = $order instanceof WC_Order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';
$items_count  = $order instanceof WC_Order ? $order->get_item_count() : 0;
$additional_content = $email->get_additional_content();

ob_start();
printf(
    esc_html__( 'הזמנה #%1$s באתר %2$s אושרה בהצלחה.', 'tapin' ),
    esc_html($order_number),
    esc_html($site_name)
);
$preheader_text = trim((string) ob_get_clean());

ob_start();
?>
<td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
    <?php esc_html_e( 'הזמנה אושרה ב', 'tapin' ); ?>
    <span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
</td>
<?php
$header_html = trim((string) ob_get_clean());

ob_start();
?>
<td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
    <?php esc_html_e('שלום,', 'tapin'); ?><br />
    <?php esc_html_e('הזמנת הלקוח אושרה והכרטיסים כבר בדרך ללקוחות.', 'tapin'); ?><br />
    <?php if ($customer_name !== '') : ?>
        <?php esc_html_e('שם הלקוח/ה:', 'tapin'); ?>
        <strong><?php echo esc_html($customer_name); ?></strong><br />
    <?php endif; ?>
    <?php if ($items_count > 0) : ?>
        <?php esc_html_e('כמות פריטים בהזמנה:', 'tapin'); ?>
        <strong><?php echo esc_html((string) $items_count); ?></strong><br />
    <?php endif; ?>
    <?php if ($total !== '') : ?>
        <?php esc_html_e('סכום חיוב כולל:', 'tapin'); ?>
        <strong><?php echo esc_html($total); ?></strong><br />
    <?php endif; ?>
</td>
<?php
$body_html = trim((string) ob_get_clean());

$button_html = '';
if ($dashboard_url !== '') {
    $button_label = esc_html__('פתח/י את ההזמנה למעקב', 'tapin');
    ob_start();
    ?>
    <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($dashboard_url); ?>">
        <?php echo esc_html($button_label); ?>
    </a>
    <?php
    $button_html = trim((string) ob_get_clean());
}

$meta_rows_html = '';
$meta_rows_html .= '';
if ($event_name !== '' || $event_date !== '' || $event_location !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <?php if ($event_name !== '') : ?>
                <strong><?php esc_html_e('?"?? ?"???', 'tapin'); ?></strong>
                <?php echo esc_html($event_name); ?><br />
            <?php endif; ?>
            <?php if ($event_date !== '') : ?>
                <strong><?php esc_html_e('?�?�?� �-�?:', 'tapin'); ?></strong>
                <?php echo esc_html($event_date); ?><br />
            <?php endif; ?>
            <?php if ($event_location !== '') : ?>
                <strong><?php esc_html_e('?�???x:', 'tapin'); ?></strong>
                <?php echo esc_html($event_location); ?><br />
            <?php endif; ?>
        </td>
    </tr>
    <?php
    $meta_rows_html .= trim((string) ob_get_clean());
}
if ($order_number !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <strong><?php esc_html_e('מספר ההזמנה:', 'tapin'); ?></strong>
            <?php echo esc_html('#' . $order_number); ?>
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
    <?php esc_html_e('צוות Tapin.', 'tapin'); ?>
</td>
<?php
$additional_html = trim((string) ob_get_clean());

ob_start();
?>
<?php esc_html_e('הודעה זו נשלחה אליך כמפיק באתר Tapin לאחר שהזמנה אושרה.', 'tapin'); ?>
<br />
<?php esc_html_e('ליצירת קשר עם התמיכה:', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
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
