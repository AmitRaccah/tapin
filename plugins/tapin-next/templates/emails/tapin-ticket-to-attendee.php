<?php
/**
 * Ticket email sent to Tapin attendees.
 *
 * @var WC_Order                                                 $order
 * @var string                                                   $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_TicketToAttendee $email
 * @var array<string,mixed>                                      $ticket
 * @var string                                                   $qr_image_url
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

$site_url = home_url('/');
$login_url = wc_get_page_permalink('myaccount');
if (empty($login_url)) {
    $login_url = $site_url;
}

$full_name = trim((string) ($ticket['full_name'] ?? ''));
$display_name = $full_name !== '' ? $full_name : esc_html__( '׳׳§׳•׳— ׳©׳׳ ׳•', 'tapin' );

$attendee_email = trim((string) ($ticket['email'] ?? ''));

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(
        esc_html__( '׳”׳–׳׳ ׳” #%s', 'tapin' ),
        (string) ($ticket['order_id'] ?? '')
    );
}

$additional_content = $email->get_additional_content();

$preheader_text = '';
ob_start();
printf(
    esc_html__( '׳‘׳¨׳•׳/׳” ׳”׳‘׳/׳” ׳%s. ׳”׳›׳¨׳˜׳™׳¡ ׳©׳׳ ׳׳•׳›׳ ׳׳¡׳¨׳™׳§׳” ׳‘׳›׳ ׳™׳¡׳”.', 'tapin' ),
    esc_html($site_name)
);
$preheader_text = trim((string) ob_get_clean());

$header_title = '';
ob_start();
?>
<?php esc_html_e('׳‘׳¨׳•׳/׳” ׳”׳‘׳/׳” ׳', 'tapin'); ?>
<span style="color: #ff0000;"><?php echo esc_html($site_name); ?></span>
<?php
$header_title = trim((string) ob_get_clean());

$body_html = '';
ob_start();
?>
<?php esc_html_e('׳”׳™׳™,', 'tapin'); ?> <strong><?php echo esc_html($display_name); ?></strong>.<br />
<?php esc_html_e('׳”׳›׳¨׳˜׳™׳¡ ׳©׳׳ ׳׳׳™׳¨׳•׳¢', 'tapin'); ?>
<strong><?php echo esc_html($label); ?></strong>
<?php esc_html_e('׳׳•׳›׳. ׳׳¦׳•׳¨׳£ ׳‘׳”׳׳©׳ ׳‘׳¨׳§׳•׳“ ׳׳¡׳¨׳™׳§׳” ׳‘׳›׳ ׳™׳¡׳”.', 'tapin'); ?>
<?php
$body_html = trim((string) ob_get_clean());

$button_label = esc_html__( '׳׳¦׳₪׳™׳™׳” ׳‘׳”׳–׳׳ ׳”', 'tapin' );
$button_url   = $login_url;

$qr_image_alt = esc_attr__( '׳‘׳¨׳§׳•׳“ ׳”׳›׳¨׳˜׳™׳¡ ׳©׳׳', 'tapin' );

$meta_rows = [];
if ($attendee_email !== '') {
    $meta_rows[esc_html__( '׳׳™׳׳™׳™׳:', 'tapin' )] = esc_html($attendee_email);
}

$additional_html = '';
if ($additional_content) {
    ob_start();
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
    $additional_html = trim((string) ob_get_clean());
}

$support_html = '';
ob_start();
?>
<?php esc_html_e('׳¦׳¨׳™׳ ׳¢׳–׳¨׳”? ׳׳₪׳©׳¨ ׳׳”׳©׳™׳‘ ׳׳׳™׳™׳ ׳–׳” ׳׳• ׳׳›׳×׳•׳‘ ׳׳ ׳• ׳‘-', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>.
<?php
$support_html = trim((string) ob_get_clean());

$footer_html = '';
ob_start();
?>
<?php
printf(
    esc_html__( '׳”׳•׳“׳¢׳” ׳–׳• ׳ ׳©׳׳—׳” ׳׳•׳×׳•׳׳×׳™׳× ׳‘׳¢׳§׳‘׳•׳× ׳”׳–׳׳ ׳” ׳‘׳׳×׳¨ %s.', 'tapin' ),
    esc_html($site_name)
);
?>
<?php esc_html_e('׳¢׳™׳•׳ ׳‘', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('׳׳×׳¨', 'tapin'); ?></a>,
<a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('׳׳“׳™׳ ׳™׳•׳× ׳”׳₪׳¨׳˜׳™׳•׳×', 'tapin'); ?></a>
<?php esc_html_e('׳•-', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="<?php echo esc_url($site_url); ?>"><?php esc_html_e('׳×׳ ׳׳™ ׳”׳©׳™׳׳•׳©', 'tapin'); ?></a>.
<?php esc_html_e('׳›׳×׳•׳‘׳× ׳§׳©׳¨:', 'tapin'); ?>
<a style="color: #ff0000; text-decoration: none;" href="mailto:support@tapin.co.il">support@tapin.co.il</a>
<?php
$footer_html = trim((string) ob_get_clean());
?>

<?php
wc_get_template(
    'emails/partials/tapin-dark-wrapper.php',
    [
        'site_name'       => $site_name,
        'site_url'        => $site_url,
        'preheader_text'  => $preheader_text,
        'header_title'    => $header_title,
        'body_html'       => $body_html,
        'qr_image_url'    => $qr_image_url,
        'qr_image_alt'    => $qr_image_alt,
        'button_label'    => $button_label,
        'button_url'      => $button_url,
        'meta_rows'       => $meta_rows,
        'additional_html' => $additional_html,
        'support_html'    => $support_html,
        'footer_html'     => $footer_html,
    ],
    '',
    trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
);
?>

<?php do_action('woocommerce_email_footer', $email); ?>