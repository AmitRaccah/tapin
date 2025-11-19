<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_ProducerTicketCheckin extends WC_Email
{
    /**
     * @var array<string,mixed>
     */
    private array $ticket = [];

    public function __construct()
    {
        $this->id             = 'tapin_producer_ticket_checkin';
        $this->title          = esc_html__( 'התראה למפיק: אורח אושר בכניסה', 'tapin' );
        $this->description    = esc_html__( 'נשלח למפיק כאשר צוות Tapin מאשר כרטיס בכניסה לאירוע.', 'tapin' );
        $this->heading        = esc_html__( 'לקוח/ה אושר/ה בכניסה לאירוע שלך', 'tapin' );
        $this->subject        = esc_html__( 'לקוח מהזמנה #%s אושר בכניסה', 'tapin' );
        $this->customer_email = false;
        $this->template_html  = '';
        $this->template_plain = '';
        $this->placeholders   = [
            '{order_number}' => '',
            '{site_title}'   => $this->get_blogname(),
        ];

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

        $this->setup_locale();

        $this->object                         = $order;
        $this->recipient                      = $recipient;
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{site_title}']   = $this->get_blogname();

        if (!$this->is_enabled() || !$this->get_recipient()) {
            $this->restore_locale();
            return;
        }

        $subject = sprintf($this->get_subject(), $this->placeholders['{order_number}']);

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
        $order       = $this->object instanceof WC_Order ? $this->object : null;
        $orderNumber = $order ? $order->get_order_number() : '';
        $attendee    = $this->resolveAttendeeName($this->ticket);
        $label       = $this->resolveTicketLabel($this->ticket);
        $approvedAt  = $this->formatApprovedAt($this->ticket);
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        ob_start();
        ?>
        <div style="direction:rtl;text-align:right;font-family:Arial,Helvetica,sans-serif;">
            <p>
                <?php
                printf(
                    esc_html__( 'המשתתף/ת %1$s אושר/ה בכניסה לכרטיס %2$s.', 'tapin' ),
                    esc_html($attendee),
                    esc_html($label)
                );
                ?>
            </p>
            <?php if ($orderNumber !== '') : ?>
                <p><?php printf( esc_html__( 'מספר הזמנה: #%s', 'tapin' ), esc_html($orderNumber) ); ?></p>
            <?php endif; ?>
            <?php if ($approvedAt !== '') : ?>
                <p><?php printf( esc_html__( 'שעת הצ׳ק-אין: %s', 'tapin' ), esc_html($approvedAt) ); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url($orderUrl); ?>" style="color:#0f766e;text-decoration:none;font-weight:600;">
                    <?php esc_html_e( 'פתח/י את ההזמנה למעקב', 'tapin' ); ?>
                </a>
            </p>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        $order       = $this->object instanceof WC_Order ? $this->object : null;
        $orderNumber = $order ? $order->get_order_number() : '';
        $attendee    = $this->resolveAttendeeName($this->ticket);
        $label       = $this->resolveTicketLabel($this->ticket);
        $approvedAt  = $this->formatApprovedAt($this->ticket);
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        $lines   = [];
        $lines[] = sprintf(esc_html__( 'המשתתף/ת %1$s אושר/ה בכניסה לכרטיס %2$s.', 'tapin' ), $attendee, $label);
        if ($orderNumber !== '') {
            $lines[] = sprintf(esc_html__( 'מספר הזמנה: #%s', 'tapin' ), $orderNumber);
        }
        if ($approvedAt !== '') {
            $lines[] = sprintf(esc_html__( 'שעת הצ׳ק-אין: %s', 'tapin' ), $approvedAt);
        }
        $lines[] = sprintf(esc_html__( 'לצפייה בהזמנה: %s', 'tapin' ), $orderUrl);

        return implode("\n", array_filter($lines));
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

    /**
     * @param array<string,mixed> $ticket
     */
    private function resolveAttendeeName(array $ticket): string
    {
        $name = trim((string) ($ticket['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($ticket['email'] ?? ''));
        }

        if ($name === '') {
            $name = esc_html__( 'אורח/ת ללא שם', 'tapin' );
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function resolveTicketLabel(array $ticket): string
    {
        $label = trim((string) ($ticket['ticket_label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($ticket['product_name'] ?? ''));
        }

        if ($label === '') {
            $label = esc_html__( 'האירוע שלך', 'tapin' );
        }

        return $label;
    }

    /**
     * @param array<string,mixed> $ticket
     */
    private function formatApprovedAt(array $ticket): string
    {
        $value = trim((string) ($ticket['approved_at'] ?? ''));
        if ($value === '') {
            return current_time('d/m/Y H:i');
        }

        $format = trim((string) get_option('date_format')) . ' ' . trim((string) get_option('time_format'));
        return mysql2date($format, $value, true);
    }

    private function buildOrderLink(WC_Order $order): string
    {
        $orderId = (int) $order->get_id();
        if ($orderId <= 0) {
            return admin_url('edit.php?post_type=shop_order');
        }

        return admin_url(sprintf('post.php?post=%d&action=edit', $orderId));
    }
}
