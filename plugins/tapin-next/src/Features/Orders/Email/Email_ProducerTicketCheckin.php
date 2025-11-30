<?php declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;
use Tapin\Events\Features\Orders\TicketEmails\TicketUrlBuilder;

final class Email_ProducerTicketCheckin extends WC_Email
{
    /**
     * @var array<string,mixed>
     */
    private array $ticket = [];
    private TicketUrlBuilder $ticketUrlBuilder;
    private string $ticketUrl = '';
    private string $qrImageUrl = '';

    public function __construct()
    {
        $this->id             = 'tapin_producer_ticket_checkin';
        $this->title          = esc_html__( 'התראה למפיק: אורח אושר בכניסה', 'tapin' );
        $this->description    = esc_html__( 'אימייל זה נשלח למפיק כאשר כרטיס של לקוח מאושר בכניסה לאירוע.', 'tapin' );
        $this->heading        = esc_html__( 'לקוח אושר בכניסה לאירוע שלך', 'tapin' );
        $this->subject        = esc_html__( 'לקוח מהזמנה #%s אושר בכניסה', 'tapin' );
        $this->customer_email = false;

        $this->template_html  = 'emails/tapin-producer-ticket-checkin.php';
        $this->template_plain = 'emails/plain/tapin-producer-ticket-checkin.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->ticketUrlBuilder = new TicketUrlBuilder();

        parent::__construct();

        add_action('tapin/events/ticket/approved_at_entry', [$this, 'trigger'], 10, 4);
    }

    /**
     * @param array<string,mixed> $ticket
     */
    public function trigger(WC_Order $order, int $producerId, string $ticketKey, array $ticket): void
    {
        $recipient = $this->resolveProducerEmail($producerId);
        if ($recipient === '') {
            return;
        }

        $this->ticket = $ticket;
        $this->ticketUrl = $this->ticketUrlBuilder->buildCheckinUrl($this->ticket);
        $this->qrImageUrl = $this->generateQrImage(
            isset($this->ticket['token']) ? (string) $this->ticket['token'] : '',
            $this->ticketUrl
        );

        $this->setup_locale();

        $this->object    = $order;
        $this->recipient = $recipient;

        if (!$this->is_enabled() || !$this->get_recipient()) {
            $this->restore_locale();
            return;
        }

        $subject = sprintf($this->get_subject(), $order->get_order_number());

        $this->send(
            $this->get_recipient(),
            $subject,
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        $this->restore_locale();
    }

    public function get_content_html(): string
    {
        ob_start();

        wc_get_template(
            'emails/tapin-producer-ticket-checkin.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'ticket'        => $this->ticket,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder(
                        $this->object,
                        $this->ticket,
                        isset($this->ticket['producer_id']) ? (int) $this->ticket['producer_id'] : null
                    )
                    : [],
                'ticket_url'    => $this->ticketUrl,
                'qr_image_url'  => $this->qrImageUrl,
            ],
            '',
            $this->template_base
        );

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        ob_start();

        wc_get_template(
            'emails/plain/tapin-producer-ticket-checkin.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'ticket'        => $this->ticket,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder(
                        $this->object,
                        $this->ticket,
                        isset($this->ticket['producer_id']) ? (int) $this->ticket['producer_id'] : null
                    )
                    : [],
                'ticket_url'    => $this->ticketUrl,
                'qr_image_url'  => $this->qrImageUrl,
            ],
            '',
            $this->template_base
        );

        return (string) ob_get_clean();
    }

    private function resolveProducerEmail(int $producerId): string
    {
        if ($producerId <= 0) {
            return '';
        }

        $producer = get_userdata($producerId);
        if (!$producer || !is_email($producer->user_email)) {
            return '';
        }

        return (string) $producer->user_email;
    }

    private function generateQrImage(string $token, string $ticketUrl): string
    {
        $token = strtolower(trim($token));
        if ($token === '' || $ticketUrl === '' || !$this->ensureQrLibrary()) {
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
