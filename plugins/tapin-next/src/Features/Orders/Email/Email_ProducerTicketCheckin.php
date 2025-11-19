<?php declare(strict_types=1);

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
        $this->description    = esc_html__( 'אימייל זה נשלח למפיק כאשר כרטיס של לקוח מאושר בכניסה לאירוע.', 'tapin' );
        $this->heading        = esc_html__( 'לקוח אושר בכניסה לאירוע שלך', 'tapin' );
        $this->subject        = esc_html__( 'לקוח מהזמנה #%s אושר בכניסה', 'tapin' );
        $this->customer_email = false;

        $this->template_html  = 'emails/tapin-producer-ticket-checkin.php';
        $this->template_plain = 'emails/plain/tapin-producer-ticket-checkin.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

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
}

