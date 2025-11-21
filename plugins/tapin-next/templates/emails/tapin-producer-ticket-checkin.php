<?php
/**
 * Producer notification when a ticket is checked in.
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var WC_Email $email
 * @var array<string,mixed> $ticket
 */

defined('ABSPATH') || exit;

$order_number = $order instanceof WC_Order ? (string) $order->get_order_number() : '';
$ticket_data  = isset($ticket) && is_array($ticket) ? $ticket : [];
$attendee     = trim((string) ($ticket_data['full_name'] ?? ''));
$ticket_label = trim((string) ($ticket_data['ticket_type_label'] ?? ($ticket_data['ticket_type'] ?? '')));
$checked_in   = trim((string) ($ticket_data['checked_in_at'] ?? ''));
// TODO: replace English copy with Hebrew text.

do_action('woocommerce_email_header', $email_heading, $email);
?>
<p><?php echo esc_html__('Ticket checked in for your event.', 'tapin'); ?></p>
<p>
    <?php if ($order_number !== '') : ?>
        <?php echo esc_html__('Order', 'tapin'); ?> #<?php echo esc_html($order_number); ?><br />
    <?php endif; ?>
    <?php if ($attendee !== '') : ?>
        <?php echo esc_html__('Attendee:', 'tapin'); ?> <?php echo esc_html($attendee); ?><br />
    <?php endif; ?>
    <?php if ($ticket_label !== '') : ?>
        <?php echo esc_html__('Ticket type:', 'tapin'); ?> <?php echo esc_html($ticket_label); ?><br />
    <?php endif; ?>
    <?php if ($checked_in !== '') : ?>
        <?php echo esc_html__('Checked in at:', 'tapin'); ?> <?php echo esc_html($checked_in); ?><br />
    <?php endif; ?>
</p>
<?php do_action('woocommerce_email_footer', $email); ?>
