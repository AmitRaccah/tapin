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
if ($site_name === '') {
    $site_name = 'Tapin';
}

$site_url = function_exists('tapin_next_canonical_site_url') ? tapin_next_canonical_site_url() : home_url('/');
$apply_canonical = static function (string $url) use ($site_url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $baseParts = wp_parse_url($site_url);
    if (!is_array($baseParts) || empty($baseParts['host'])) {
        return $url;
    }

    $parts    = wp_parse_url($url);
    $path     = is_array($parts) && isset($parts['path']) ? $parts['path'] : '/' . ltrim($url, '/');
    $query    = is_array($parts) && !empty($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = is_array($parts) && !empty($parts['fragment']) ? '#' . $parts['fragment'] : '';

    $scheme = $baseParts['scheme'] ?? 'https';
    $host   = $baseParts['host'];
    $port   = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

    return $scheme . '://' . $host . $port . $path . $query . $fragment;
};

$login_url = $apply_canonical((string) wc_get_page_permalink('myaccount'));
if ($login_url === '') {
    $login_url = $site_url;
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
$view_order_url = $apply_canonical($view_order_url);
if ($view_order_url === '') {
    $view_order_url = $login_url;
}

$full_name    = trim((string) ($ticket['full_name'] ?? ''));
$display_name = $full_name !== '' ? $full_name : esc_html__('אורח Tapin', 'tapin');

$label = (string) ($ticket['ticket_label'] ?? ($ticket['product_name'] ?? ''));
if ($label === '') {
    $label = sprintf(
        esc_html__('הזמנה #%s', 'tapin'),
        (string) ($ticket['order_id'] ?? '')
    );
}

$event_context  = isset($event_context) && is_array($event_context) ? $event_context : [];
$event_name     = trim((string) ($event_context['event_name'] ?? ''));
$event_date     = trim((string) ($event_context['event_date_label'] ?? ''));
$event_address  = trim((string) ($event_context['event_address'] ?? ''));
$event_city     = trim((string) ($event_context['event_city'] ?? ''));
$event_location = trim($event_address . ($event_city !== '' ? ' ' . $event_city : ''));

$ticket_url = isset($ticket_url) ? $apply_canonical((string) $ticket_url) : '';
if ($ticket_url === '' && $view_order_url !== '') {
    $ticket_url = $view_order_url;
}
if ($ticket_url === '' && $login_url !== '') {
    $ticket_url = $login_url;
}

$qr_image_url = isset($qr_image_url) ? $apply_canonical((string) $qr_image_url) : '';

$display_name_plain   = trim(wp_strip_all_tags($display_name));
$label_plain          = trim(wp_strip_all_tags($label));
$site_name_plain      = trim(wp_strip_all_tags($site_name));
$event_name_plain     = trim(wp_strip_all_tags($event_name));
$event_date_plain     = trim(wp_strip_all_tags($event_date));
$event_location_plain = trim(wp_strip_all_tags($event_location));
$ticket_url_plain     = $ticket_url !== '' ? esc_url_raw($ticket_url) : '';
$qr_image_plain       = $qr_image_url !== '' ? esc_url_raw($qr_image_url) : '';

echo sprintf(__('שלום %s,', 'tapin'), $display_name_plain) . "\n\n";
echo sprintf(__('הכרטיס שלך ל-%s מוכן ומצורף.', 'tapin'), $label_plain) . "\n";
echo __('פתח את תמונת ה-QR המצורפת או את הקישור הבטוח לצפייה בכרטיס.', 'tapin') . "\n\n";

if ($event_name_plain !== '' || $event_date_plain !== '' || $event_location_plain !== '') {
    if ($event_name_plain !== '') {
        echo sprintf(__('שם האירוע: %s', 'tapin'), $event_name_plain) . "\n";
    }
    if ($event_date_plain !== '') {
        echo sprintf(__('תאריך האירוע: %s', 'tapin'), $event_date_plain) . "\n";
    }
    if ($event_location_plain !== '') {
        echo sprintf(__('מיקום: %s', 'tapin'), $event_location_plain) . "\n";
    }
    echo "\n";
}

if ($qr_image_plain !== '') {
    echo sprintf(__('תמונת ה-QR: %s', 'tapin'), $qr_image_plain) . "\n\n";
} elseif ($ticket_url_plain !== '') {
    echo sprintf(__('לא הצלחנו ליצור QR, אפשר לפתוח את הכרטיס כאן: %s', 'tapin'), $ticket_url_plain) . "\n\n";
}

if ($ticket_url_plain !== '') {
    echo sprintf(__('קישור לצפייה בכרטיס: %s', 'tapin'), $ticket_url_plain) . "\n\n";
}

echo sprintf(__('תודה שבחרת ב-%s!', 'tapin'), $site_name_plain) . "\n";
echo __('לשאלות נוספות: support@tapin.co.il', 'tapin') . "\n\n";

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
