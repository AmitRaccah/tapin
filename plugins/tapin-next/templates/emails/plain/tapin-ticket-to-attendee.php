<?php
/**
 * Plain text ticket email for Tapin attendees.
 *
 * @var WC_Order                                                  $order
 * @var string                                                    $email_heading
 * @var Tapin\Events\Features\Orders\Email\Email_TicketToAttendee $email
 * @var array<string,mixed>                                       $ticket
 * @var string                                                    $qr_image_url
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
    $login_url = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
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
$display_name = $full_name !== '' ? $full_name : esc_html__( '?????- Tapin', 'tapin' );

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(
        esc_html__( '?"?-???�?" #%s', 'tapin' ),
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

$display_name_plain  = trim(wp_strip_all_tags($display_name));
$label_plain         = trim(wp_strip_all_tags($label));
$site_name_plain     = trim(wp_strip_all_tags($site_name !== '' ? $site_name : 'Tapin'));
$event_name_plain    = trim(wp_strip_all_tags($event_name));
$event_date_plain    = trim(wp_strip_all_tags($event_date));
$event_location_plain = trim(wp_strip_all_tags($event_location));
$ticket_url_plain    = $ticket_url !== '' ? esc_url_raw($ticket_url) : '';

echo sprintf( __( '?c????? %s,', 'tapin' ), $display_name_plain ) . "\n\n";
echo sprintf( __( '?"?>?"?~?T?� ?c???? ?????T?"??� %s ????>?? ????-?>?" ????. ???�??"?� ?`?"???" ???�?"?T??" ?`?>?�?T?�?".', 'tapin' ), $label_plain ) . "\n\n";

if ($event_name_plain !== '' || $event_date_plain !== '' || $event_location_plain !== '') {
    if ($event_name_plain !== '') {
        echo sprintf( __( '?c?? ?"???T?"??�: %s', 'tapin' ), $event_name_plain ) . "\n";
    }
    if ($event_date_plain !== '') {
        echo sprintf( __( '?x???"?T?? ??c?�?" %s', 'tapin' ), $event_date_plain ) . "\n";
    }
    if ($event_location_plain !== '') {
        echo sprintf( __( '???T????: %s', 'tapin' ), $event_location_plain ) . "\n";
    }
    echo "\n";
}

if ($qr_image_url !== '') {
    echo sprintf( __( '?`?"???" ???�???T?T?": %s', 'tapin' ), esc_url_raw( $qr_image_url ) ) . "\n\n";
} elseif ($ticket_url_plain !== '') {
    echo sprintf( __( '??�? QR? ???x ?"?>?"?~?T?� ?`?????�?�??x ?"??T?c??": %s', 'tapin' ), $ticket_url_plain ) . "\n\n";
}

if ($ticket_url_plain !== '') {
    echo sprintf( __( '???�???T?T?" ?`?"?-???�?" ?c????: %s', 'tapin' ), $ticket_url_plain ) . "\n\n";
}

echo sprintf( __( '?x??"?" ?c?`?-?"?x ?`-%s!', 'tapin' ), $site_name_plain ) . "\n";
echo __( '?�?"?T?? ?�?-?"?"? ?????c?" ???"?c?T?` ?????T?T?? ?"?-?" ??? ???>?x??` ??-support@tapin.co.il', 'tapin' ) . "\n\n";

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

