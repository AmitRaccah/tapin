<?php declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_TicketToAttendee extends WC_Email
{
    /**
     * @var array<string,mixed>
     */
    private array $ticket = [];

    private string $qrImageUrl = '';

    public function __construct()
    {
        $this->id             = 'tapin_ticket_to_attendee';
        $this->title          = esc_html__( 'כרטיס ללקוח (Tapin)', 'tapin' );
        $this->description    = esc_html__( 'אימייל זה נשלח לכל משתתף עם כרטיס אישי וקוד QR לסריקה בכניסה.', 'tapin' );

        $this->customer_email = true;
        $this->template_html  = 'emails/tapin-ticket-to-attendee.php';
        $this->template_plain = 'emails/plain/tapin-ticket-to-attendee.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{site_title}'   => $this->get_blogname(),
        ];

        $this->subject        = esc_html__( 'הכרטיס שלך לאירוע %s מוכן', 'tapin' );
        $this->heading        = esc_html__( 'הכרטיס שלך מוכן!', 'tapin' );

        $this->template_base = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->supports = [
            'manual',
            'wpml',
        ];

        parent::__construct();
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        if (isset($this->form_fields['enabled'])) {
            $this->form_fields['enabled']['title'] = esc_html__( 'הפעלת אימייל', 'tapin' );
            $this->form_fields['enabled']['label'] = esc_html__( 'שליחת כרטיס (עם קוד QR) לכל משתתף לאחר אישור התשלום.', 'tapin' );
        }

        if (isset($this->form_fields['subject'])) {
            $this->form_fields['subject']['title']       = esc_html__( 'נושא', 'tapin' );
            $this->form_fields['subject']['description'] = esc_html__( 'אפשר להשתמש ב-%s כדי לשלב את שם האתר או שם האירוע בנושא.', 'tapin' );
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
            $this->form_fields['additional_content']['description'] = esc_html__( 'טקסט שיופיע בסוף האימייל, למשל הוראות הגעה או פרטי יצירת קשר.', 'tapin' );
            $this->form_fields['additional_content']['placeholder'] = esc_html__( 'תודה שבחרת ב-Tapin.', 'tapin' );
            $this->form_fields['additional_content']['default']     = esc_html__( 'תודה שבחרת ב-Tapin.', 'tapin' );
            $this->form_fields['additional_content']['css']         = 'width:400px; height:75px;';
        }
    }

    /**
     * @param array<string,mixed> $ticket
     */
    public function trigger(WC_Order $order, array $ticket, string $qrImageUrl = ''): void
    {
        $this->object     = $order;
        $this->ticket     = $ticket;
        $this->qrImageUrl = $qrImageUrl;
        $this->recipient  = sanitize_email((string) ($ticket['email'] ?? ''));
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{site_title}']   = $this->get_blogname();

        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }

        $label   = $this->resolveTicketLabel($ticket);
        $subject = sprintf($this->get_subject(), $label);

        $this->send(
            $this->get_recipient(),
            $subject,
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );
    }

    public function get_content_html(): string
    {
        ob_start();

        wc_get_template(
            $this->template_html,
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'ticket'        => $this->ticket,
                'qr_image_url'  => $this->qrImageUrl,
            ],
            '',
            trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
        );

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        ob_start();

        wc_get_template(
            $this->template_plain,
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'ticket'        => $this->ticket,
                'qr_image_url'  => $this->qrImageUrl,
            ],
            '',
            trailingslashit(TAPIN_NEXT_PATH) . 'templates/'
        );

        return (string) ob_get_clean();
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
            $label = sprintf(
                esc_html__( 'כרטיס #%s', 'tapin' ),
                (string) ( $ticket['order_id'] ?? '' )
            );
        }

        return sanitize_text_field($label);
    }
}

