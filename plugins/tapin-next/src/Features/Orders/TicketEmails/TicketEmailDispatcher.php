<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\TicketEmails;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\Email\Email_TicketToAttendee;
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

        $emailObj = $this->getTicketEmail();
        if ($emailObj === null) {
            return;
        }

        foreach ($tokenized as $ticketKey => $ticketData) {
            $ticketKey = (string) $ticketKey;
            if ($ticketKey === '' || isset($sentIndex[$ticketKey])) {
                continue;
            }

            $email = sanitize_email((string) ($ticketData['email'] ?? ''));
            if ($email === '' || !is_email($email)) {
                continue;
            }

            $ticketData['email'] = $email;

            $ticketUrl = $this->urlBuilder->build($ticketData);
            if ($ticketUrl === '') {
                continue;
            }

            $qrImageUrl = $this->generateQrImage((string) ($ticketData['token'] ?? ''), $ticketUrl);

            $emailObj->trigger($order, $ticketData, $qrImageUrl);

            $sentIndex[$ticketKey] = true;
            $sentKeys[]            = $ticketKey;
            $changed               = true;
        }

        if ($changed) {
            $order->update_meta_data('_tapin_ticket_emails_sent', array_values(array_unique($sentKeys)));
            $order->save();
        }
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

    private function getTicketEmail(): ?Email_TicketToAttendee
    {
        if (!function_exists('WC')) {
            return null;
        }

        try {
            $mailer = WC()->mailer();
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_object($mailer) || !method_exists($mailer, 'get_emails')) {
            return null;
        }

        $emails = $mailer->get_emails();
        if (!is_array($emails)) {
            return null;
        }

        $email = $emails['tapin_ticket_to_attendee'] ?? null;

        return $email instanceof Email_TicketToAttendee ? $email : null;
    }
}

