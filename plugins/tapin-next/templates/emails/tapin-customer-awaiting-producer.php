<?php
/**
 * Customer email for awaiting producer approval status.
 *
 * @var WC_Order                                                          $order
 * @var string                                                            $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_CustomerAwaitingProducer $email
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
        $customer_name = trim((string) $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    }
}
if ($customer_name === '') {
    $customer_name = esc_html__( 'לקוח Tapin', 'tapin' );
}

$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$order_number       = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$additional_content = $email->get_additional_content();

$preheader_text = '';
ob_start();
printf(
    esc_html__( 'ההזמנה שלך ב-%s התקבלה וממתינה לאישור מפיק.', 'tapin' ),
    esc_html( $site_name )
);
$preheader_text = trim((string) ob_get_clean());

$header_html = '';
ob_start();
?>
<td style="background: #151515; padding: 18px 22px; font-family: Arial,Helvetica,sans-serif; color: #ff0000; font-size: 20px; font-weight: 800;">
    <?php esc_html_e( 'ההזמנה נקלטה ב', 'tapin' ); ?>
    <span style="color: #ff0000;"><?php echo esc_html( $site_name ); ?></span>
</td>
<?php
$header_html = trim((string) ob_get_clean());

$body_html = '';
ob_start();
?>
<td style="padding: 26px 24px 8px 24px; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 15px; line-height: 1.7; background: #121212;">
    <?php esc_html_e( 'שלום', 'tapin' ); ?>
    <strong><?php echo esc_html( $customer_name ); ?></strong>,<br />
    <?php esc_html_e( 'ההזמנה שלך התקבלה בהצלחה וממתינה כעת לאישור הסופי של המפיק.', 'tapin' ); ?><br />
    <?php esc_html_e( 'מיד לאחר האישור יישלח אליך אימייל נוסף עם הכרטיסים ו-QR קוד כניסה לכל משתתף.', 'tapin' ); ?><br />
    <?php esc_html_e( 'אם יש שאלה או שמשהו נראה לא תקין, אפשר לפנות אלינו במייל התמיכה של Tapin.', 'tapin' ); ?>
</td>
<?php
$body_html = trim((string) ob_get_clean());

$button_html = '';
if ($view_order_url !== '') {
    $button_label = esc_html__( 'לצפייה בהזמנה שלך', 'tapin' );
    ob_start();
    ?>
    <a style="background: #ff0000; color: #111; text-decoration: none; padding: 12px 18px; border-radius: 8px; font-weight: 800;" href="<?php echo esc_url($view_order_url); ?>">
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
                <strong><?php esc_html_e( 'שם האירוע:', 'tapin' ); ?></strong>
                <?php echo esc_html( $event_name ); ?><br />
            <?php endif; ?>
            <?php if ($event_date !== '') : ?>
                <strong><?php esc_html_e( 'תאריך ושעה:', 'tapin' ); ?></strong>
                <?php echo esc_html( $event_date ); ?><br />
            <?php endif; ?>
            <?php if ($event_location !== '') : ?>
                <strong><?php esc_html_e( 'מיקום:', 'tapin' ); ?></strong>
                <?php echo esc_html( $event_location ); ?><br />
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
            <strong><?php esc_html_e( 'מספר הזמנה:', 'tapin' ); ?></strong>
            <?php echo esc_html('#' . $order_number); ?>
        </td>
    </tr>
    <?php
    $meta_rows_html .= trim((string) ob_get_clean());
}

$additional_html = '';
ob_start();
?>
<td style="padding: 0 24px 24px 24px; background: #121212; font-family: Arial,Helvetica,sans-serif; color: #e6e6e6; font-size: 14px; line-height: 1.8;">
    <?php if ($additional_content) : ?>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
        <br /><br />
    <?php endif; ?>
    <?php esc_html_e( 'צריך עזרה? אפשר להשיב למייל זה או לכתוב לנו ב-', 'tapin' ); ?>
    <a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
</td>
<?php
$additional_html = trim((string) ob_get_clean());

$footer_html = '';
ob_start();
?>
<?php
printf(
    esc_html__( 'הודעה זו נשלחה אוטומטית בעקבות הזמנה באתר %s בזמן שההזמנה ממתינה לאישור מפיק.', 'tapin' ),
    esc_html( $site_name )
);
?>
<br />
<?php esc_html_e( 'הצוות של Tapin תמיד כאן בשבילך.', 'tapin' ); ?>
<br />
<?php esc_html_e( 'לאתר שלנו:', 'tapin' ); ?>
<a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a>
<?php
$footer_html = trim((string) ob_get_clean());
?>

<?php
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
    trailingslashit( TAPIN_NEXT_PATH ) . 'templates/'
);
?>

<?php do_action('woocommerce_email_footer', $email); ?>


