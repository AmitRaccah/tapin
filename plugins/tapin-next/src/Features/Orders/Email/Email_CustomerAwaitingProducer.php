<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use Tapin\Events\Features\Orders\AwaitingProducerStatus;
use Tapin\Events\Support\AttendeeSecureStorage;
use WC_Email;
use WC_Order;
use WC_Order_Item_Product;

final class Email_CustomerAwaitingProducer extends WC_Email
{
    public function __construct()
    {
        $this->id             = 'tapin_customer_awaiting_producer';
        $this->title          = esc_html__( 'הזמנה ממתינה לאישור מפיק (Tapin)', 'tapin' );
        $this->description    = esc_html__( 'נשלח ללקוח ולמוזמנים מהפופאפ כאשר ההזמנה נמצאת במצב "ממתין לאישור מפיק".', 'tapin' );

        $this->customer_email = true;

        $this->template_html  = 'emails/tapin-customer-awaiting-producer.php';
        $this->template_plain = 'emails/plain/tapin-customer-awaiting-producer.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->subject = esc_html__( 'ההזמנה שלך ב-%s ממתינה לאישור מפיק', 'tapin' );
        $this->heading = esc_html__( 'ההזמנה שלך התקבלה וממתינה לאישור מפיק', 'tapin' );

        $this->supports = [
            'wpml',
        ];

        parent::__construct();

        add_action(
            'woocommerce_order_status_' . AwaitingProducerStatus::STATUS_SLUG,
            [$this, 'trigger'],
            10,
            2
        );
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        if (isset($this->form_fields['enabled'])) {
            $this->form_fields['enabled']['title'] = esc_html__( 'הפעלת אימייל', 'tapin' );
            $this->form_fields['enabled']['label'] = esc_html__( 'שליחת הודעת "הזמנה ממתינה לאישור מפיק" ללקוח ולמוזמנים', 'tapin' );
        }

        if (isset($this->form_fields['subject'])) {
            $this->form_fields['subject']['title']       = esc_html__( 'נושא', 'tapin' );
            $this->form_fields['subject']['description'] = esc_html__( 'אפשר להשתמש ב-%s כדי לשלב את שם האתר או האירוע.', 'tapin' );
            $this->form_fields['subject']['placeholder'] = $this->subject;
            $this->form_fields['subject']['default']     = $this->subject;
        }

        if (isset($this->form_fields['heading'])) {
            $this->form_fields['heading']['title']       = esc_html__( 'כותרת', 'tapin' );
            $this->form_fields['heading']['description'] = esc_html__( 'כותרת שתופיע בראש ההודעה.', 'tapin' );
            $this->form_fields['heading']['default']     = $this->heading;
        }

        if (isset($this->form_fields['additional_content'])) {
            $this->form_fields['additional_content']['title']       = esc_html__( 'תוכן נוסף', 'tapin' );
            $this->form_fields['additional_content']['description'] = esc_html__( 'טקסט שיופיע בסוף האימייל, לפני שורת התמיכה.', 'tapin' );
            $this->form_fields['additional_content']['placeholder'] = esc_html__( 'תודה שבחרת ב-Tapin.', 'tapin' );
            $this->form_fields['additional_content']['default']     = esc_html__( 'תודה שבחרת ב-Tapin.', 'tapin' );
        }
    }

    /**
     * @param int|WC_Order $order
     */
    public function trigger($order, $orderObj = false): void
    {
        if (is_numeric($order)) {
            $order = wc_get_order((int) $order);
        } elseif ($orderObj instanceof WC_Order) {
            $order = $orderObj;
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $this->setup_locale();

        $recipients = $this->collect_recipient_emails($order);
        if ($recipients === []) {
            $this->restore_locale();
            return;
        }

        $this->recipient = implode(',', $recipients);
        $this->object    = $order;

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

        $this->restore_locale();
    }

    /**
     * @return array<int,string>
     */
    private function collect_recipient_emails(WC_Order $order): array
    {
        $emails = [];

        $billing = sanitize_email((string) $order->get_billing_email());
        if ($billing !== '' && is_email($billing)) {
            $emails[$billing] = $billing;
        }

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $decoded = [];

            $encrypted = (string) $item->get_meta('_tapin_attendees_json', true);
            if ($encrypted !== '') {
                $decoded = AttendeeSecureStorage::decrypt($encrypted);
            }

            if ($decoded === []) {
                $aggregate = $order->get_meta('_tapin_attendees', true);
                $decoded   = AttendeeSecureStorage::extractFromAggregate($aggregate, $item);
            }

            foreach ($decoded as $attendee) {
                if (!is_array($attendee)) {
                    continue;
                }

                $email = sanitize_email((string) ($attendee['email'] ?? ''));
                if ($email !== '' && is_email($email)) {
                    $emails[$email] = $email;
                }
            }
        }

        return array_values($emails);
    }

    public function get_content_html(): string
    {
        ob_start();

        wc_get_template(
            'emails/tapin-customer-awaiting-producer.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
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
            'emails/plain/tapin-customer-awaiting-producer.php',
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
            ],
            '',
            $this->template_base
        );

        return (string) ob_get_clean();
    }
}
