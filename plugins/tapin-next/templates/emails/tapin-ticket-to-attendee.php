<?php
/**
 * Ticket email sent to Tapin attendees.
 *
 * @var WC_Order                                                  $order
 * @var string                                                    $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_TicketToAttendee $email
 * @var array<string,mixed>                                       $ticket
 * @var string                                                    $qr_image_url
 */

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);

$site_name = trim((string) $email->get_blogname());
if ($site_name === '') {
    $site_name = get_bloginfo('name', 'display');
}
if ($site_name === '') {
    $site_name = get_bloginfo('name');
}

$site_url  = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
$login_url = wc_get_page_permalink('myaccount');
if (empty($login_url)) {
    $login_url = $site_url;
}

$view_order_url = '';
if ($order instanceof WC_Order) {
    if (method_exists($order, 'get_view_order_url')) {
        $view_order_url = (string) $order->get_view_order_url();
    }

    if ($view_order_url === '') {
        $view_order_url = wc_get_endpoint_url('view-order', (string) $order->get_id(), $login_url);
    }
}
if ($view_order_url === '') {
    $view_order_url = $login_url;
}

$full_name    = trim((string) ($ticket['full_name'] ?? ''));
$display_name = $full_name !== '' ? $full_name : esc_html__('לקוח Tapin', 'tapin');

$attendee_email = trim((string) ($ticket['email'] ?? ''));

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(
        esc_html__('הזמנה #%s', 'tapin'),
        (string) ($ticket['order_id'] ?? '')
    );
}

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$ticket_url = isset($ticket_url) ? (string) $ticket_url : '';
if ($ticket_url === '' && $view_order_url !== '') {
    $ticket_url = $view_order_url;
}
if ($ticket_url === '' && $login_url !== '') {
    $ticket_url = $login_url;
}

$additional_content = $email->get_additional_content();

$button_label = esc_html__('מעבר לאזור האישי', 'tapin');
$button_url   = $ticket_url !== '' ? $ticket_url : $login_url;

$qr_image_alt = esc_attr__('קוד QR להצגת הכרטיס באירוע', 'tapin');

$preheader_text = '';
ob_start();
printf(
    esc_html__('ברוך/ה הבא/ה ל-%s. הכרטיס שלך לאירוע מצורף במייל זה.', 'tapin'),
    esc_html($site_name)
);
$preheader_text = trim((string) ob_get_clean());

$header_html = '';
ob_start();
?>
<td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
    <?php esc_html_e('כרטיס לאירוע ב', 'tapin'); ?>
    <span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
</td>
<?php
$header_html = trim((string) ob_get_clean());

$body_html = '';
ob_start();
?>
<td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
    <?php esc_html_e('היי', 'tapin'); ?>
    <strong><?php echo esc_html($display_name); ?></strong>.<br />
    <?php esc_html_e('הכרטיס שלך לאירוע', 'tapin'); ?>
    <strong><?php echo esc_html($label); ?></strong>
    <?php esc_html_e('מוכן ומצורף כאן למטה.', 'tapin'); ?><br />
    <?php esc_html_e('בכניסה לאירוע הציגו את הברקוד המצורף לצוות האירוע.', 'tapin'); ?><br />
    <?php esc_html_e('מומלץ לשמור את האימייל הזה עד לסיום האירוע.', 'tapin'); ?>
</td>
<?php
$body_html = trim((string) ob_get_clean());

$qr_image_html = '';
if (!empty($qr_image_url)) {
    ob_start();
    ?>
    <img src="<?php echo esc_url($qr_image_url); ?>" alt="<?php echo esc_attr($qr_image_alt); ?>" style="max-width:260px;height:auto;" />
    <?php
    $qr_image_html = trim((string) ob_get_clean());
}

$button_html = '';
if (!empty($button_url)) {
    ob_start();
    ?>
    <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($button_url); ?>">
        <?php echo esc_html($button_label); ?>
    </a>
    <?php
    $button_html = trim((string) ob_get_clean());
}

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';

$meta_rows_html = '';
if ($attendee_email !== '' || $order_number !== '' || $event_name !== '' || $event_date !== '' || $event_location !== '' || $ticket_url !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <?php if ($event_name !== '') : ?>
                <strong><?php esc_html_e('שם האירוע:', 'tapin'); ?></strong>
                <?php echo esc_html($event_name); ?><br />
            <?php endif; ?>
            <?php if ($event_date !== '') : ?>
                <strong><?php esc_html_e('תאריך ושעה:', 'tapin'); ?></strong>
                <?php echo esc_html($event_date); ?><br />
            <?php endif; ?>
            <?php if ($event_location !== '') : ?>
                <strong><?php esc_html_e('מיקום:', 'tapin'); ?></strong>
                <?php echo esc_html($event_location); ?><br />
            <?php endif; ?>
            <?php if ($attendee_email !== '') : ?>
                <strong><?php esc_html_e('אימייל:', 'tapin'); ?></strong>
                <?php echo esc_html($attendee_email); ?><br />
            <?php endif; ?>
            <?php if ($order_number !== '') : ?>
                <strong><?php esc_html_e('מספר ההזמנה:', 'tapin'); ?></strong>
                <?php echo esc_html('#' . $order_number); ?><br />
            <?php endif; ?>
            <?php if ($ticket_url !== '') : ?>
                <strong><?php esc_html_e('קישור לכרטיס:', 'tapin'); ?></strong>
                <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($ticket_url); ?>"><?php echo esc_html($ticket_url); ?></a>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    $meta_rows_html = trim((string) ob_get_clean());
}

$qr_fallback_html = '';
if (empty($qr_image_url) && $button_url !== '') {
    ob_start();
    ?>
    <tr>
        <td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
            <strong><?php esc_html_e('הקוד לא נוצר? אין בעיה.', 'tapin'); ?></strong><br />
            <?php esc_html_e('הציגו את הכרטיס באמצעות הקישור הבא בכניסה לאירוע:', 'tapin'); ?><br />
            <a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_url); ?></a>
        </td>
    </tr>
    <?php
    $qr_fallback_html = trim((string) ob_get_clean());
}

$additional_html = '';
ob_start();
?>
<td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
    <?php if ($additional_content) : ?>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        <br /><br />
    <?php endif; ?>
    <?php esc_html_e('צריך עזרה? אפשר להשיב למייל זה או לכתוב לנו ב-', 'tapin'); ?>
    <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>.
</td>
<?php
$additional_html = trim((string) ob_get_clean());

$footer_html = '';
ob_start();
?>
<?php
printf(
    esc_html__('הודעה זו נשלחה אוטומטית בעקבות אישור ההזמנה באתר %s.', 'tapin'),
    esc_html($site_name)
);
?>
<br />
<?php esc_html_e('הצוות של Tapin מאחל לך בילוי נעים.', 'tapin'); ?>
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
        'qr_image_html'   => $qr_image_html,
        'button_html'     => $button_html,
        'meta_rows_html'  => $meta_rows_html . $qr_fallback_html,
        'additional_html' => $additional_html,
        'footer_html'     => $footer_html,
    ],
    '',
    trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
);

do_action('woocommerce_email_footer', $email);

