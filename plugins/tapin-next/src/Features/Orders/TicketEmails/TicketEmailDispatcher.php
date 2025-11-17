<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\TicketEmails;

use Tapin\Events\Core\Service;
use WC_Order;

final class TicketEmailDispatcher implements Service
{
    private TicketAttendeesResolver $attendeesResolver;
    private TicketTokensRepository $tokensRepository;
    private TicketUrlBuilder $urlBuilder;

    public function __construct(
        ?TicketAttendeesResolver $attendeesResolver = null,
        ?TicketTokensRepository $tokensRepository = null,
        ?TicketUrlBuilder $urlBuilder = null
    ) {
        $this->attendeesResolver = $attendeesResolver ?: new TicketAttendeesResolver();
        $this->tokensRepository  = $tokensRepository ?: new TicketTokensRepository();
        $this->urlBuilder        = $urlBuilder ?: new TicketUrlBuilder();
    }

    public function register(): void
    {
        add_action(
            'tapin/events/order/approved_by_producer',
            [$this, 'handleOrderApproved'],
            20,
            2
        );
    }

    public function handleOrderApproved(WC_Order $order, int $producerId): void
    {
        if ($producerId <= 0) {
            return;
        }

        $tickets = $this->attendeesResolver->resolve($order, $producerId);
        if ($tickets === []) {
            return;
        }

        $tokenized = $this->tokensRepository->createTokensForOrder($order, $producerId, $tickets);
        if ($tokenized === []) {
            return;
        }

        $sentKeys  = $this->normalizeSentList($order->get_meta('_tapin_ticket_emails_sent', true));
        $sentIndex = array_fill_keys($sentKeys, true);
        $changed   = false;

        foreach ($tokenized as $ticketKey => $ticketData) {
            $ticketKey = (string) $ticketKey;
            if ($ticketKey === '' || isset($sentIndex[$ticketKey])) {
                continue;
            }

            $email = sanitize_email((string) ($ticketData['email'] ?? ''));
            if ($email === '' || !is_email($email)) {
                continue;
            }

            $ticketUrl = $this->urlBuilder->build($ticketData);
            if ($ticketUrl === '') {
                continue;
            }

            $qrImageUrl = $this->generateQrImage((string) ($ticketData['token'] ?? ''), $ticketUrl);
            $label      = $this->resolveTicketLabel($ticketData);
            $subject    = sprintf(
                /* translators: %s: ticket or event label */
                esc_html__( 'הכרטיס שלך לאירוע %s', 'tapin' ),
                $label
            );

            $fullName = trim((string) ($ticketData['full_name'] ?? ''));
            $greeting = $fullName !== ''
                ? sprintf( esc_html__( 'שלום %s,', 'tapin' ), esc_html( $fullName ) )
                : esc_html__( 'שלום,', 'tapin' );

            $body  = '<div style="direction:rtl;text-align:right;font-family:Arial,sans-serif;font-size:16px;line-height:1.6;">';
            $body .= '<p>' . $greeting . '</p>';
            $body .= '<p>' . sprintf(
                esc_html__( 'הכרטיס שלך לאירוע %s מוכן. מצורף ברקוד לסריקה בכניסה.', 'tapin' ),
                esc_html( $label )
            ) . '</p>';

            if ($qrImageUrl !== '') {
                $body .= '<p style="text-align:center;"><img src="' . esc_url( $qrImageUrl ) . '" alt="' . esc_attr__( 'ברקוד הכרטיס שלך', 'tapin' ) . '" style="max-width:260px;height:auto;" /></p>';
            }

            $body .= '<p>' . esc_html__( 'נתראה במסיבה!', 'tapin' ) . '</p>';
            $body .= '</div>';

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent    = wp_mail($email, wp_specialchars_decode($subject), $body, $headers);

            if ($sent) {
                $sentIndex[$ticketKey] = true;
                $sentKeys[]            = $ticketKey;
                $changed               = true;
            }
        }

        if ($changed) {
            $order->update_meta_data('_tapin_ticket_emails_sent', array_values(array_unique($sentKeys)));
            $order->save();
        }
    }

    private function resolveTicketLabel(array $ticket): string
    {
        $label = (string) ($ticket['ticket_label'] ?? '');
        if ($label === '') {
            $label = (string) ($ticket['product_name'] ?? '');
        }
        if ($label === '') {
            $label = sprintf(esc_html__('כרטיס #%s', 'tapin'), (string) ($ticket['order_id'] ?? ''));
        }

        return sanitize_text_field($label);
    }

    private function normalizeSentList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $entry) {
            $key = is_string($entry) ? trim($entry) : '';
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    private function generateQrImage(string $token, string $ticketUrl): string
    {
        $token = trim($token);
        if ($token === '' || !$this->ensureQrLibrary()) {
            return '';
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return '';
        }

        $dir = trailingslashit($uploads['basedir']) . 'tapin-tickets';
        if (!wp_mkdir_p($dir)) {
            return '';
        }

        $filename = 'tapin_ticket_' . md5($token) . '.png';
        $filePath = trailingslashit($dir) . $filename;

        try {
            $level = defined('QR_ECLEVEL_M') ? \QR_ECLEVEL_M : \QR_ECLEVEL_L;
            \QRcode::png($ticketUrl, $filePath, $level, 4, 2);
        } catch (\Throwable $e) {
            return '';
        }

        $url = trailingslashit($uploads['baseurl']) . 'tapin-tickets/' . $filename;
        if (file_exists($filePath)) {
            $mtime = (int) @filemtime($filePath);
            if ($mtime > 0) {
                $url = add_query_arg('v', $mtime, $url);
            }
        }

        return $url;
    }

    private function ensureQrLibrary(): bool
    {
        if (class_exists('\QRcode')) {
            return true;
        }

        if (!defined('WP_PLUGIN_DIR')) {
            return false;
        }

        $path = trailingslashit(WP_PLUGIN_DIR) . 'generate-qr-code/phpqrcode/qrlib.php';
        if (file_exists($path)) {
            require_once $path;
        }

        return class_exists('\QRcode');
    }
}

