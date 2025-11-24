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
$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$ticket_url    = isset($ticket_url) ? (string) $ticket_url : '';
$qr_image_url  = isset($qr_image_url) ? (string) $qr_image_url : '';

do_action('woocommerce_email_header', $email_heading, $email);
?>
<p><?php echo esc_html__('A ticket was checked in for your event.', 'tapin'); ?></p>
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
    <?php if ($event_name !== '') : ?>
        <?php echo esc_html__('Event:', 'tapin'); ?> <?php echo esc_html($event_name); ?><br />
    <?php endif; ?>
    <?php if ($event_date !== '') : ?>
        <?php echo esc_html__('Event date:', 'tapin'); ?> <?php echo esc_html($event_date); ?><br />
    <?php endif; ?>
    <?php if ($event_address !== '' || $event_city !== '') : ?>
        <?php echo esc_html__('Location:', 'tapin'); ?>
        <?php echo esc_html(trim($event_address . ' ' . $event_city)); ?><br />
    <?php endif; ?>
</p>
<?php if ($ticket_url !== '') : ?>
    <p>
        <a href="<?php echo esc_url($ticket_url); ?>" target="_blank" rel="noopener">
            <?php echo esc_html__('View ticket', 'tapin'); ?>
        </a>
    </p>
<?php endif; ?>

<?php if ($qr_image_url !== '') : ?>
    <p>
        <?php echo esc_html__('Ticket QR:', 'tapin'); ?><br />
        <img src="<?php echo esc_url($qr_image_url); ?>" alt="<?php echo esc_attr__('Ticket QR code', 'tapin'); ?>" style="max-width:180px;height:auto;" />
    </p>
<?php endif; ?>
<?php do_action('woocommerce_email_footer', $email); ?>
