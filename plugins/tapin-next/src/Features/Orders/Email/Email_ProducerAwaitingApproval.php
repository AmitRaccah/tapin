<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Support\Orders;
use WC_Email;
use WC_Order;

final class Email_ProducerAwaitingApproval extends WC_Email
{
    public function __construct()
    {
        $this->id             = 'tapin_producer_order_awaiting';
        $this->title          = esc_html__( 'אישור מפיק: הזמנה מחכה לאישורך', 'tapin' );
        $this->description    = esc_html__( 'נשלח בזמן אמת למפיק כשיש הזמנה עם תשלום מושהה שממתינה לאישורו.', 'tapin' );
        $this->heading        = esc_html__( 'יש הזמנה שממתינה לאישורך', 'tapin' );
        $this->subject        = esc_html__( 'הזמנה #%s ממתינה לאישור שלך', 'tapin' );
        $this->customer_email = false;

        $this->template_html  = 'emails/tapin-producer-awaiting-approval.php';
        $this->template_plain = 'emails/plain/tapin-producer-awaiting-approval.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        parent::__construct();

        add_action('tapin/events/order/awaiting_producer', [$this, 'trigger'], 10, 2);

        add_action(
            'woocommerce_order_status_' . AwaitingProducerStatus::STATUS_SLUG,
            [$this, 'triggerFromStatus'],
            10,
            2
        );
    }

    /**
     * @param int|WC_Order $order
     */
    public function triggerFromStatus($order, $orderObj = null): void
    {
        if (is_numeric($order)) {
            $order = wc_get_order((int) $order);
        } elseif ($orderObj instanceof WC_Order) {
            $order = $orderObj;
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $producerIds = Orders::collectProducerIds($order);
        foreach ($producerIds as $producerId) {
            $producerId = (int) $producerId;
            if ($producerId > 0) {
                $this->trigger($order, $producerId);
            }
        }
    }

    public function trigger(WC_Order $order, int $producerId): void
    {
        $recipient = $this->resolveProducerEmail($producerId);
        if ($recipient === '') {
            return;
        }

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
            'emails/tapin-producer-awaiting-approval.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder($this->object)
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
            'emails/plain/tapin-producer-awaiting-approval.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder($this->object)
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
