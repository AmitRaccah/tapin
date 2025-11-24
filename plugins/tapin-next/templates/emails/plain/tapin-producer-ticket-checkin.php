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
$event_context = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name    = trim((string) ($event_context['event_name'] ?? ''));
$event_date    = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address = trim((string) ($event_context['event_address'] ?? ''));
$event_city    = trim((string) ($event_context['event_city'] ?? ''));
$ticket_url    = isset($ticket_url) ? (string) $ticket_url : '';
$qr_image_url  = isset($qr_image_url) ? (string) $qr_image_url : '';

echo esc_html__('A ticket was checked in.', 'tapin') . "\n";
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
if ($event_name !== '') {
    echo esc_html__('Event:', 'tapin') . ' ' . $event_name . "\n";
}
if ($event_date !== '') {
    echo esc_html__('Event date:', 'tapin') . ' ' . $event_date . "\n";
}
if ($event_address !== '' || $event_city !== '') {
    echo esc_html__('Location:', 'tapin') . ' ' . trim($event_address . ' ' . $event_city) . "\n";
}
if ($ticket_url !== '') {
    echo esc_html__('View ticket:', 'tapin') . ' ' . $ticket_url . "\n";
}
if ($qr_image_url !== '') {
    echo esc_html__('Ticket QR:', 'tapin') . ' ' . $qr_image_url . "\n";
}
