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

$fullName = trim((string) ($ticket['full_name'] ?? ''));
$greeting = $fullName !== ''
    ? sprintf(esc_html__('שלום %s,', 'tapin'), esc_html($fullName))
    : esc_html__('שלום,', 'tapin');

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(esc_html__('כרטיס #%s', 'tapin'), (string) ($ticket['order_id'] ?? ''));
}
$label = esc_html($label);

?>

<p><?php echo esc_html($greeting); ?></p>

<p><?php echo sprintf(esc_html__('הכרטיס שלך ל-%s מוכן. נשמח לראותך באירוע.', 'tapin'), $label); ?></p>

<?php if ($qr_image_url !== '') : ?>
    <p style="text-align:center;">
        <img src="<?php echo esc_url($qr_image_url); ?>" alt="<?php echo esc_attr__('קוד QR לכרטיס', 'tapin'); ?>" style="max-width:260px;height:auto;" />
    </p>
<?php endif; ?>

<?php if ($email->get_additional_content()) : ?>
    <div><?php echo wp_kses_post(wpautop(wptexturize($email->get_additional_content()))); ?></div>
<?php endif; ?>

<?php do_action('woocommerce_email_footer', $email); ?>

