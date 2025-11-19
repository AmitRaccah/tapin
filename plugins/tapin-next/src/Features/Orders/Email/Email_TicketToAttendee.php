<?php
declare(strict_types=1);

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
        $this->title          = esc_html__('כרטיס ללקוח (Tapin)', 'tapin');
        $this->description    = esc_html__('שליחת כרטיס דיגיטלי ללקוח לאחר שאושר על ידי המפיק.', 'tapin');
        $this->customer_email = true;
        $this->template_html  = 'emails/tapin-ticket-to-attendee.php';
        $this->template_plain = 'emails/plain/tapin-ticket-to-attendee.php';
        $this->placeholders   = [
            '{order_number}' => '',
            '{site_title}'   => $this->get_blogname(),
        ];

        $this->subject = esc_html__('הכרטיס שלך לאירוע %s', 'tapin');
        $this->heading = esc_html__('הכרטיס שלך מוכן', 'tapin');

        parent::__construct();
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled'            => [
                'title'   => esc_html__('הפעלה/כיבוי', 'tapin'),
                'type'    => 'checkbox',
                'label'   => esc_html__('שליחת כרטיסי Tapin ללקוחות', 'tapin'),
                'default' => 'yes',
            ],
            'subject'            => [
                'title'       => esc_html__('נושא', 'tapin'),
                'type'        => 'text',
                'description' => esc_html__('שימוש ב-%s עבור תיאור האירוע או הכרטיס.', 'tapin'),
                'placeholder' => esc_html__('הכרטיס שלך לאירוע %s', 'tapin'),
                'default'     => esc_html__('הכרטיס שלך לאירוע %s', 'tapin'),
            ],
            'heading'            => [
                'title'       => esc_html__('כותרת', 'tapin'),
                'type'        => 'text',
                'description' => esc_html__('הכותרת שתופיע בראש ההודעה.', 'tapin'),
                'default'     => esc_html__('הכרטיס שלך מוכן', 'tapin'),
            ],
            'additional_content' => [
                'title'       => esc_html__('תוכן נוסף', 'tapin'),
                'description' => esc_html__('יופיע מתחת להודעה הראשית.', 'tapin'),
                'css'         => 'width:400px; height:75px;',
                'placeholder' => esc_html__('תודה שבחרת ב-Tapin.', 'tapin'),
                'type'        => 'textarea',
                'default'     => esc_html__('תודה שבחרת ב-Tapin.', 'tapin'),
            ],
        ];
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
            $label = sprintf(esc_html__('כרטיס #%s', 'tapin'), (string) ($ticket['order_id'] ?? ''));
        }

        return sanitize_text_field($label);
    }
}

