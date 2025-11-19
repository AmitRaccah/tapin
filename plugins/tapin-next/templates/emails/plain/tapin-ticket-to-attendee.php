<?php
/**
 * Plain text ticket email for Tapin attendees.
 *
 * @var WC_Order                                                 $order
 * @var string                                                   $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_TicketToAttendee $email
 * @var array<string,mixed>                                      $ticket
 * @var string                                                   $qr_image_url
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

$login_url = wc_get_page_permalink('myaccount');
if (empty($login_url)) {
    $login_url = home_url('/');
}

$full_name    = trim((string) ($ticket['full_name'] ?? ''));
$display_name = $full_name !== '' ? $full_name : esc_html__( 'לקוח Tapin', 'tapin' );

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(
        esc_html__( 'הזמנה #%s', 'tapin' ),
        (string) ($ticket['order_id'] ?? '')
    );
}

$display_name_plain = trim(wp_strip_all_tags($display_name));
$label_plain        = trim(wp_strip_all_tags($label));
$site_name_plain    = trim(wp_strip_all_tags($site_name !== '' ? $site_name : 'Tapin'));

echo sprintf( __( 'שלום %s,', 'tapin' ), $display_name_plain ) . "\n\n";
echo sprintf( __( 'הכרטיס שלך לאירוע %s מוכן ומחכה לך. מצורף ברקוד לסריקה בכניסה.', 'tapin' ), $label_plain ) . "\n\n";

if ($qr_image_url !== '') {
    echo sprintf( __( 'ברקוד לצפייה: %s', 'tapin' ), esc_url_raw( $qr_image_url ) ) . "\n\n";
}

echo sprintf( __( 'לצפייה בהזמנה שלך: %s', 'tapin' ), esc_url_raw( $login_url ) ) . "\n\n";
echo sprintf( __( 'תודה שבחרת ב-%s!', 'tapin' ), $site_name_plain ) . "\n";
echo __( 'צריך עזרה? אפשר להשיב למייל הזה או לכתוב ל-support@tapin.co.il', 'tapin' ) . "\n\n";

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
