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

$fullName = trim((string) ($ticket['full_name'] ?? ''));
$greeting = $fullName !== ''
    ? sprintf(esc_html__('שלום %s,', 'tapin'), esc_html($fullName))
    : esc_html__('שלום,', 'tapin');

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(esc_html__('כרטיס #%s', 'tapin'), (string) ($ticket['order_id'] ?? ''));
}
$label = esc_html($label);

echo esc_html($greeting) . "\n\n";
echo sprintf(esc_html__('הכרטיס שלך ל-%s מוכן. נשמח לראותך באירוע.', 'tapin'), $label) . "\n\n";

if ($qr_image_url !== '') {
    echo sprintf(esc_html__('קישור לקוד ה-QR שלך: %s', 'tapin'), esc_url_raw($qr_image_url)) . "\n\n";
}

$additional = $email->get_additional_content();
if ($additional) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional))) . "\n\n";
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

