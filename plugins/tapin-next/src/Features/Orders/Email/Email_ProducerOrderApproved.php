<?php declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_ProducerOrderApproved extends WC_Email
{
    private int $producerId = 0;
    public function __construct()
    {
        $this->id             = 'tapin_producer_order_approved';
        $this->title          = esc_html__( 'התראה למפיק: הזמנה אושרה', 'tapin' );
        $this->description    = esc_html__( 'אימייל זה נשלח למפיק כאשר הוא מאשר הזמנה במערכת Tapin.', 'tapin' );
        $this->heading        = esc_html__( 'הזמנה אושרה בהצלחה', 'tapin' );
        $this->subject        = esc_html__( 'הזמנה #%s אושרה בהצלחה', 'tapin' );
        $this->customer_email = false;

        $this->template_html  = 'emails/tapin-producer-order-approved.php';
        $this->template_plain = 'emails/plain/tapin-producer-order-approved.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        parent::__construct();

        add_action('tapin/events/order/approved_by_producer', [$this, 'trigger'], 10, 2);
    }

    public function trigger(WC_Order $order, int $producerId): void
    {
        $recipient = $this->resolveProducerEmail($producerId);
        if ($recipient === '') {
            return;
        }

        $this->setup_locale();

        $this->object     = $order;
        $this->recipient  = $recipient;
        $this->producerId = $producerId;

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
            'emails/tapin-producer-order-approved.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder($this->object, [], $this->producerId)
                    : [],
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
            'emails/plain/tapin-producer-order-approved.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder($this->object, [], $this->producerId)
                    : [],
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
