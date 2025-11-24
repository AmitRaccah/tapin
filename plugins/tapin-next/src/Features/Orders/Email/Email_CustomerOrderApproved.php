<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Features\Orders\AwaitingProducerGate;
use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use WC_Email;
use WC_Order;

final class Email_CustomerOrderApproved extends WC_Email
{
    private int $producerId = 0;

    public function __construct()
    {
        $this->id             = 'tapin_customer_order_approved';
        $this->title          = esc_html__('הזמנה אושרה (Tapin)', 'tapin');
        $this->description    = esc_html__('אימייל זה נשלח ללקוח המשלם כאשר המפיק אישר את כל המשתתפים בהזמנה, עם סיכום הכרטיסים והסכום הסופי שחויב.', 'tapin');
        $this->customer_email = true;

        $this->template_html  = 'emails/tapin-customer-order-approved.php';
        $this->template_plain = 'emails/plain/tapin-customer-order-approved.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->subject = esc_html__('ההזמנה שלך ב-%s אושרה', 'tapin');
        $this->heading = esc_html__('ההזמנה שלך אושרה', 'tapin');

        $this->supports = ['wpml'];

        parent::__construct();

        add_action('tapin/events/order/approved_by_producer', [$this, 'trigger'], 10, 2);
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        if (isset($this->form_fields['enabled'])) {
            $this->form_fields['enabled']['title'] = esc_html__('הפעלת אימייל', 'tapin');
            $this->form_fields['enabled']['label'] = esc_html__('שליחת הודעה כאשר ההזמנה אושרה במלואה על ידי המפיק.', 'tapin');
        }

        if (isset($this->form_fields['subject'])) {
            $this->form_fields['subject']['title']       = esc_html__('נושא', 'tapin');
            $this->form_fields['subject']['description'] = esc_html__('ניתן להשתמש ב-%s כדי לשלב את שם האתר.', 'tapin');
            $this->form_fields['subject']['placeholder'] = $this->subject;
            $this->form_fields['subject']['default']     = $this->subject;
        }

        if (isset($this->form_fields['heading'])) {
            $this->form_fields['heading']['title']       = esc_html__('כותרת', 'tapin');
            $this->form_fields['heading']['description'] = esc_html__('כותרת שתופיע בראש ההודעה.', 'tapin');
            $this->form_fields['heading']['default']     = $this->heading;
        }

        if (isset($this->form_fields['additional_content'])) {
            $this->form_fields['additional_content']['title']       = esc_html__('תוכן נוסף', 'tapin');
            $this->form_fields['additional_content']['description'] = esc_html__('טקסט שיופיע בסוף האימייל, למשל הערות חשובות.', 'tapin');
            $this->form_fields['additional_content']['placeholder'] = esc_html__('תודה שבחרת ב-Tapin.', 'tapin');
            $this->form_fields['additional_content']['default']     = esc_html__('תודה שבחרת ב-Tapin.', 'tapin');
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
        $this->producerId = $producerId;

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
            'emails/plain/tapin-customer-order-approved.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'producer_id'   => $this->producerId,
                'event_context' => $this->object instanceof WC_Order
                    ? EmailEventContext::fromOrder($this->object, [], $this->producerId)
                    : [],
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
}
