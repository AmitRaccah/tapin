<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Orders\TicketEmails\TicketAttendeesResolver;
use Tapin\Events\Features\Orders\TicketEmails\TicketTokensRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\Time;
use WC_Order;

final class TicketCheckin implements Service
{
    private TicketTokensRepository $tokensRepository;
    private TicketAttendeesResolver $attendeesResolver;

    public function __construct(
        ?TicketTokensRepository $tokensRepository = null,
        ?TicketAttendeesResolver $attendeesResolver = null
    ) {
        $this->tokensRepository  = $tokensRepository ?: new TicketTokensRepository();
        $this->attendeesResolver = $attendeesResolver ?: new TicketAttendeesResolver();
    }

    public function register(): void
    {
        add_shortcode('tapin_ticket_checkin', [$this, 'render']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
    }

    public function registerQueryVar(array $vars): array
    {
        $vars[] = 'tapin_ticket';
        return array_values(array_unique($vars));
    }

    public function render($atts = []): string
    {
        $token = $this->detectToken();
        if ($token === '') {
            return $this->renderMessage(esc_html__('לא נמצא קוד כרטיס בבקשה.', 'tapin'));
        }

        $lookup = $this->tokensRepository->findTicketByToken($token);
        if ($lookup === null || empty($lookup['ticket']) || empty($lookup['order'])) {
            return $this->renderMessage(esc_html__('הכרטיס לא נמצא או אינו תקין.', 'tapin'));
        }

        /** @var WC_Order $order */
        $order = $lookup['order'];
        if (!$order instanceof WC_Order) {
            return $this->renderMessage(esc_html__('הכרטיס לא נמצא או אינו תקין.', 'tapin'));
        }

        $ticket    = (array) $lookup['ticket'];
        $ticketKey = (string) ($lookup['ticket_key'] ?? '');
        if ($ticketKey === '') {
            return $this->renderMessage(esc_html__('הכרטיס לא נמצא או אינו תקין.', 'tapin'));
        }

        if (!$this->isAuthorized($ticket)) {
            status_header(403);
            return $this->renderMessage(
                esc_html__( 'אין לך הרשאה לצפות בכרטיס זה. אם את/ה המפיק, היכנס/י למערכת ונסה שוב.', 'tapin' )
            );
        }

        $error = $this->maybeHandleApproval($ticket, $order, $ticketKey, $token);

        return $this->renderTicketCard($ticket, $order, $token, $error);
    }

    private function detectToken(): string
    {
        if (isset($_POST['tapin_ticket_token'])) {
            $token = sanitize_text_field(wp_unslash((string) $_POST['tapin_ticket_token']));
            if ($token !== '') {
                return $token;
            }
        }

        $queryToken = get_query_var('tapin_ticket');
        if (is_string($queryToken) && $queryToken !== '') {
            return sanitize_text_field($queryToken);
        }

        if (isset($_GET['tapin_ticket'])) {
            $token = sanitize_text_field(wp_unslash((string) $_GET['tapin_ticket']));
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    private function renderMessage(string $message): string
    {
        return '<div class="tapin-ticket-message" style="direction:rtl;text-align:right;padding:16px;border:1px solid #e5e5e5;border-radius:8px;background:#fff;">' . esc_html($message) . '</div>';
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function isAuthorized(array $ticket): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }

        $producerId = isset($ticket['producer_id']) ? (int) $ticket['producer_id'] : 0;
        return $producerId > 0 && get_current_user_id() === $producerId;
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function maybeHandleApproval(array $ticket, WC_Order $order, string $ticketKey, string $token): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return '';
        }

        $action = isset($_POST['tapin_ticket_action']) ? sanitize_text_field(wp_unslash((string) $_POST['tapin_ticket_action'])) : '';
        if ($action !== 'approve') {
            return '';
        }

        $nonce = isset($_POST['tapin_ticket_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['tapin_ticket_nonce']))
            : '';
        if (!wp_verify_nonce($nonce, 'tapin_ticket_approve')) {
            return esc_html__('הבקשה לא אושרה. נסה/י לרענן את הדף ולנסות שוב.', 'tapin');
        }

        $this->tokensRepository->markTicketApproved($order, $ticketKey);

        wp_safe_redirect($this->buildRedirectUrl($token));
        exit;
    }

    private function buildRedirectUrl(string $token): string
    {
        $current = $this->currentUrl();
        $clean   = remove_query_arg(['tapin_ticket_action', 'tapin_ticket_nonce', 'tapin_ticket_token'], $current);
        return add_query_arg(['tapin_ticket' => $token], $clean);
    }

    private function currentUrl(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        if ($requestUri === '') {
            return home_url('/');
        }

        return home_url($requestUri);
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function renderTicketCard(array $ticket, WC_Order $order, string $token, string $error): string
    {
        $status     = isset($ticket['status']) ? (string) $ticket['status'] : 'pending';
        $isApproved = $status === 'approved';
        $eventLabel = $this->resolveTicketLabel($ticket);
        $eventTs    = $this->resolveEventTimestamp((int) ($ticket['product_id'] ?? 0));
        $attendee   = $this->findAttendeeDetails($order, $ticket);
        $details    = $this->buildDetails($attendee, $ticket, $order);

        ob_start();
        ?>
        <div class="tapin-ticket-card" style="direction:rtl;max-width:640px;margin:0 auto;border:1px solid #e0e0e0;border-radius:16px;padding:24px;background:#fff;box-shadow:0 4px 25px rgba(0,0,0,0.08);font-family:inherit;">
            <div class="tapin-ticket-card__header" style="display:flex;flex-direction:column;gap:4px;margin-bottom:16px;">
                <h2 style="margin:0;font-size:24px;font-weight:700;"><?php echo esc_html($eventLabel); ?></h2>
                <?php if ($eventTs > 0): ?>
                    <span style="color:#666;font-size:15px;"><?php echo Time::fmtLocal($eventTs); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($error !== ''): ?>
                <div style="margin-bottom:16px;padding:12px;border-radius:8px;background:#fff4f4;border:1px solid #f5c6c6;color:#b71c1c;"><?php echo esc_html($error); ?></div>
            <?php endif; ?>
            <?php if ($isApproved): ?>
                <div style="margin-bottom:16px;padding:14px;border-radius:10px;background:#e6f7ed;border:1px solid #7cc5a4;color:#1b7d4f;font-size:20px;font-weight:700;text-align:center;">
                    <?php echo esc_html__('אושר', 'tapin'); ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom:16px;padding:14px;border-radius:10px;background:#fff8e1;border:1px solid #ffd062;color:#a15c00;font-size:18px;font-weight:600;text-align:center;">
                    <?php echo esc_html__('ממתין לאישור', 'tapin'); ?>
                </div>
                <form method="post" style="text-align:center;margin-bottom:20px;">
                    <input type="hidden" name="tapin_ticket_token" value="<?php echo esc_attr($token); ?>" />
                    <input type="hidden" name="tapin_ticket_action" value="approve" />
                    <?php wp_nonce_field('tapin_ticket_approve', 'tapin_ticket_nonce'); ?>
                    <button type="submit" style="background:#111;color:#fff;border:none;border-radius:999px;padding:12px 36px;font-size:17px;cursor:pointer;">
                        <?php echo esc_html__('אשר כניסה', 'tapin'); ?>
                    </button>
                </form>
            <?php endif; ?>
            <div class="tapin-ticket-card__details" style="border-top:1px solid #f0f0f0;padding-top:16px;">
                <?php foreach ($details as $label => $value): ?>
                    <?php if ($value === '') { continue; } ?>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:15px;gap:12px;">
                        <strong style="font-weight:600;"><?php echo esc_html($label); ?></strong>
                        <span style="color:#333;"><?php echo esc_html($value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,string> $attendee
     * @param array<string,mixed>  $ticket
     */
    private function buildDetails(array $attendee, array $ticket, WC_Order $order): array
    {
        $details = [
            esc_html__('שם מלא', 'tapin')          => $this->formatAttendeeValue('full_name', $attendee['full_name'] ?? (string) ($ticket['full_name'] ?? '')),
            esc_html__('אימייל', 'tapin')          => $this->formatAttendeeValue('email', $attendee['email'] ?? (string) ($ticket['email'] ?? '')),
            esc_html__('טלפון', 'tapin')           => $this->formatAttendeeValue('phone', $attendee['phone'] ?? (string) ($ticket['phone'] ?? '')),
            esc_html__('תאריך לידה', 'tapin')      => $this->formatAttendeeValue('birth_date', $attendee['birth_date'] ?? ''),
            esc_html__('מגדר', 'tapin')            => $this->formatAttendeeValue('gender', $attendee['gender'] ?? ''),
            esc_html__('תעודת זהות', 'tapin')      => $this->formatAttendeeValue('id_number', $attendee['id_number'] ?? ''),
            esc_html__('אינסטגרם', 'tapin')        => $this->formatAttendeeValue('instagram', $attendee['instagram'] ?? ''),
            esc_html__('טיקטוק', 'tapin')          => $this->formatAttendeeValue('tiktok', $attendee['tiktok'] ?? ''),
            esc_html__('פייסבוק', 'tapin')         => $this->formatAttendeeValue('facebook', $attendee['facebook'] ?? ''),
            esc_html__('סוג כרטיס', 'tapin')       => sanitize_text_field((string) ($ticket['ticket_type'] ?? '')),
            esc_html__('כיתוב כרטיס', 'tapin')     => sanitize_text_field((string) ($ticket['ticket_label'] ?? '')),
            esc_html__('מספר הזמנה', 'tapin')      => '#' . $order->get_order_number(),
        ];

        return $details;
    }

    private function formatAttendeeValue(string $fieldKey, string $raw): string
    {
        $raw = (string) $raw;
        if ($raw === '') {
            return '';
        }

        if ($fieldKey === 'email') {
            return sanitize_email($raw);
        }

        $display = AttendeeFields::displayValue($fieldKey, $raw);
        if ($display !== '') {
            return $display;
        }

        return sanitize_text_field($raw);
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function resolveTicketLabel(array $ticket): string
    {
        $label = (string) ($ticket['ticket_label'] ?? '');
        if ($label === '') {
            $label = (string) ($ticket['product_name'] ?? '');
        }
        if ($label === '') {
            $label = sprintf(esc_html__('כרטיס #%s', 'tapin'), (string) ($ticket['order_id'] ?? ''));
        }

        return $label;
    }

    private function resolveEventTimestamp(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        return Time::productEventTs($productId);
    }

    /**
     * @param array<string,mixed> $ticket
     * @return array<string,string>
     */
    private function findAttendeeDetails(WC_Order $order, array $ticket): array
    {
        $producerId = isset($ticket['producer_id']) ? (int) $ticket['producer_id'] : 0;
        if ($producerId <= 0) {
            return [];
        }

        $records = $this->attendeesResolver->resolve($order, $producerId);
        if ($records === []) {
            return [];
        }

        $itemId        = isset($ticket['line_item_id']) ? (int) $ticket['line_item_id'] : (int) ($ticket['item_id'] ?? 0);
        $attendeeIndex = isset($ticket['attendee_index']) ? (int) $ticket['attendee_index'] : -1;

        foreach ($records as $record) {
            if ((int) ($record['line_item_id'] ?? 0) === $itemId && (int) ($record['attendee_index'] ?? -1) === $attendeeIndex) {
                return isset($record['attendee']) && is_array($record['attendee'])
                    ? array_map('strval', $record['attendee'])
                    : [];
            }
        }

        return [];
    }
}

