<?php
/**
 * Plain text producer notification for ticket check-in.
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
// TODO: translate these labels to Hebrew.

echo esc_html__('Ticket checked in.', 'tapin') . "\n";
if ($order_number !== '') {
    echo esc_html__('Order', 'tapin') . ' #' . $order_number . "\n";
}
if ($attendee !== '') {
    echo esc_html__('Attendee:', 'tapin') . ' ' . $attendee . "\n";
}
if ($ticket_label !== '') {
    echo esc_html__('Ticket type:', 'tapin') . ' ' . $ticket_label . "\n";
}
if ($checked_in !== '') {
    echo esc_html__('Checked in at:', 'tapin') . ' ' . $checked_in . "\n";
}
