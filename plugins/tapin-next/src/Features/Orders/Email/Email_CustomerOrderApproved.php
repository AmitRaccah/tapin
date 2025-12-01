<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Features\Orders\TicketEmails\TicketTokensRepository;
use Tapin\Events\Features\Orders\TicketEmails\TicketUrlBuilder;
use WC_Email;
use WC_Order;

final class Email_CustomerOrderApproved extends WC_Email
{
    /**
     * @var array<int,string>
     */
    protected $supports = [];

    private int $producerId = 0;
    private string $ticketUrl = '';
    private string $qrImageUrl = '';
    private TicketTokensRepository $tokensRepository;
    private TicketUrlBuilder $urlBuilder;

    public function __construct()
    {
        $this->id             = 'tapin_customer_order_approved';
        $this->title          = esc_html__('אישור הזמנה (Tapin)', 'tapin');
        $this->description    = esc_html__('אימייל זה נשלח ללקוח המשלם כאשר המפיק אישר את כל המשתתפים בהזמנה, עם קישור ל-QR או לצפייה בטוחה בכרטיס.', 'tapin');
        $this->customer_email = true;

        $this->template_html  = 'emails/tapin-customer-order-approved.php';
        $this->template_plain = 'emails/plain/tapin-customer-order-approved.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->subject = esc_html__('ההזמנה שלך ב-%s אושרה', 'tapin');
        $this->heading = esc_html__('ההזמנה שלך אושרה', 'tapin');

        $this->supports = ['wpml'];

        $this->tokensRepository = new TicketTokensRepository();
        $this->urlBuilder       = new TicketUrlBuilder();

        parent::__construct();

        // Run after ticket emails so tokens are available.
        add_action('tapin/events/order/approved_by_producer', [$this, 'trigger'], 30, 2);
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        if (isset($this->form_fields['enabled'])) {
            $this->form_fields['enabled']['title'] = esc_html__('הפעלת הודעה זו', 'tapin');
            $this->form_fields['enabled']['label'] = esc_html__('שליחת הודעת אישור ללקוח לאחר שהמפיק אישר את ההזמנה.', 'tapin');
        }

        if (isset($this->form_fields['subject'])) {
            $this->form_fields['subject']['title']       = esc_html__('נושא', 'tapin');
            $this->form_fields['subject']['description'] = esc_html__('השתמשו במשתנה %s כדי לשלב את שם האתר או האירוע.', 'tapin');
            $this->form_fields['subject']['placeholder'] = $this->subject;
            $this->form_fields['subject']['default']     = $this->subject;
        }

        if (isset($this->form_fields['heading'])) {
            $this->form_fields['heading']['title']       = esc_html__('כותרת', 'tapin');
            $this->form_fields['heading']['description'] = esc_html__('כותרת שתופיע בראש המייל.', 'tapin');
            $this->form_fields['heading']['default']     = $this->heading;
        }

        if (isset($this->form_fields['additional_content'])) {
            $this->form_fields['additional_content']['title']       = esc_html__('תוכן נוסף', 'tapin');
            $this->form_fields['additional_content']['description'] = esc_html__('יופיע בסוף המייל לפני פרטי התמיכה.', 'tapin');
            $this->form_fields['additional_content']['placeholder'] = esc_html__('תודה שבחרתם בטאפין.', 'tapin');
            $this->form_fields['additional_content']['default']     = esc_html__('תודה שבחרתם בטאפין.', 'tapin');
        }
    }

    public function trigger(WC_Order $order, int $producerId): void
    {
        $recipient = $this->resolvePayerEmail($order);
        if ($recipient === '') {
            return;
        }

        if ((int) $order->get_meta('_tapin_full_approval_email_sent', true)) {
            return;
        }

        if (!AwaitingProducerGate::allProducersApproved($order)) {
            return;
        }

        $status = $order->get_status();
        if (in_array($status, [AwaitingProducerStatus::STATUS_SLUG, PartiallyApprovedStatus::STATUS_SLUG], true)) {
            return;
        }

        $this->setup_locale();

        $this->object     = $order;
        $this->recipient  = $recipient;
        $this->producerId = $this->resolvePrimaryProducerId($order);
        $this->ticketUrl  = '';
        $this->qrImageUrl = '';

        if (!$this->is_enabled() || !$this->get_recipient()) {
            $this->restore_locale();
            return;
        }

        $siteName = trim((string) $this->get_blogname());
        if ($siteName === '') {
            $siteName = get_bloginfo('name', 'display');
        }
        if ($siteName === '') {
            $siteName = get_bloginfo('name');
        }
        if ($siteName === '') {
            $siteName = 'Tapin';
        }

        $subject = sprintf($this->get_subject(), $siteName);

        $this->resolveTicketLinks($order);

        $this->send(
            $this->get_recipient(),
            $subject,
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        $order->update_meta_data('_tapin_full_approval_email_sent', 1);
        $order->save();

        $this->restore_locale();
    }

    public function get_content_html(): string
    {
        ob_start();

        wc_get_template(
            'emails/tapin-customer-order-approved.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'producer_id'   => $this->producerId,
                'event_context' => $this->object instanceof WC_Order ? EmailEventContext::fromOrder($this->object) : [],
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
            'emails/plain/tapin-customer-order-approved.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'producer_id'   => $this->producerId,
                'event_context' => $this->object instanceof WC_Order ? EmailEventContext::fromOrder($this->object) : [],
                'ticket_url'    => $this->ticketUrl,
                'qr_image_url'  => $this->qrImageUrl,
            ],
            '',
            $this->template_base
        );

        return (string) ob_get_clean();
    }

    private function resolvePayerEmail(WC_Order $order): string
    {
        $email = sanitize_email((string) $order->get_billing_email());
        if ($email !== '' && is_email($email)) {
            return $email;
        }

        $userId = (int) $order->get_user_id();
        if ($userId > 0) {
            $user = get_userdata($userId);
            if ($user && is_email($user->user_email)) {
                return (string) $user->user_email;
            }
        }

        return '';
    }

    private function resolvePrimaryProducerId(WC_Order $order): int
    {
        $ids = AwaitingProducerGate::ensureProducerMeta($order);
        if (!is_array($ids) || $ids === []) {
            return 0;
        }

        $ids = array_values(array_filter(array_map('intval', $ids), static function ($id): bool {
            return $id > 0;
        }));

        sort($ids);

        return (int) ($ids[0] ?? 0);
    }

    private function resolveTicketLinks(WC_Order $order): void
    {
        $tokens = $this->tokensRepository->getTokensForOrder($order);
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }

            $producerId = isset($ticket['producer_id']) ? (int) $ticket['producer_id'] : 0;
            if ($this->producerId > 0 && $producerId !== $this->producerId) {
                continue;
            }

            $this->ticketUrl  = $this->urlBuilder->buildViewUrl($ticket);
            $checkinUrl       = $this->urlBuilder->buildCheckinUrl($ticket);
            $this->qrImageUrl = $this->generateQrImage((string) ($ticket['token'] ?? ''), $checkinUrl);
            $this->qrImageUrl = $this->canonicalizeEmailUrl($this->qrImageUrl);
            break;
        }
    }

    private function canonicalizeEmailUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !function_exists('tapin_next_canonical_site_url')) {
            return $url;
        }

        $canonicalBase = tapin_next_canonical_site_url();
        if ($canonicalBase === '') {
            return $url;
        }

        $canonicalParts = wp_parse_url($canonicalBase);
        $parts          = wp_parse_url($url);

        if (!is_array($canonicalParts) || !is_array($parts) || empty($canonicalParts['host']) || empty($parts['path'])) {
            return $url;
        }

        $scheme   = $canonicalParts['scheme'] ?? 'https';
        $host     = $canonicalParts['host'];
        $port     = isset($canonicalParts['port']) ? ':' . $canonicalParts['port'] : '';
        $query    = !empty($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = !empty($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $port . $parts['path'] . $query . $fragment;
    }

    private function generateQrImage(string $token, string $ticketUrl): string
    {
        $token = trim($token);
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
