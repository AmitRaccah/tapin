<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_ProducerAwaitingApproval extends WC_Email
{
    public function __construct()
    {
        $this->id             = 'tapin_producer_order_awaiting';
        $this->title          = esc_html__( 'התראה למפיק: הזמנה ממתינה לאישור', 'tapin' );
        $this->description    = esc_html__( 'אימייל זה נשלח למפיק כאשר הזמנה חדשה עוברת לסטטוס "ממתין לאישור מפיק".', 'tapin' );
        $this->heading        = esc_html__( 'יש הזמנה שממתינה לאישור שלך', 'tapin' );
        $this->subject        = esc_html__( 'הזמנה #%s ממתינה לאישור שלך', 'tapin' );
        $this->customer_email = false;
        $this->template_html  = '';
        $this->template_plain = '';
        $this->placeholders   = [
            '{order_number}' => '',
            '{customer_name}' => '',
            '{site_title}'   => $this->get_blogname(),
        ];

        parent::__construct();

        add_action('tapin/events/order/awaiting_producer', [$this, 'trigger'], 10, 2);
    }

    public function trigger(WC_Order $order, int $producerId): void
    {
        $recipient = $this->resolveProducerEmail($producerId);
        if ($recipient === '') {
            return;
        }

        $this->setup_locale();

        $this->object                         = $order;
        $this->recipient                      = $recipient;
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{customer_name}'] = $this->resolveCustomerName($order);
        $this->placeholders['{site_title}']    = $this->get_blogname();

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
        $customer    = $this->placeholders['{customer_name}'] ?? '';
        $siteTitle   = $this->placeholders['{site_title}'] ?? '';
        $total       = $order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        ob_start();
        ?>
        <div style="direction:rtl;text-align:right;font-family:Arial,Helvetica,sans-serif;">
            <p>
                <?php
                printf(
                    esc_html__( 'הזמנה #%1$s באתר %2$s ממתינה לאישור שלך.', 'tapin' ),
                    esc_html($orderNumber),
                    esc_html($siteTitle)
                );
                ?>
            </p>
            <?php if ($customer !== '') : ?>
                <p><?php printf( esc_html__( 'שם הלקוח/ה: %s', 'tapin' ), esc_html($customer) ); ?></p>
            <?php endif; ?>
            <?php if ($total !== '') : ?>
                <p><?php printf( esc_html__( 'סכום העסקה: %s', 'tapin' ), esc_html($total) ); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url($orderUrl); ?>" style="color:#d63638;text-decoration:none;font-weight:600;">
                    <?php esc_html_e( 'פתח/י את ההזמנה בלוח הבקרה', 'tapin' ); ?>
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
        $customer    = $this->placeholders['{customer_name}'] ?? '';
        $siteTitle   = $this->placeholders['{site_title}'] ?? '';
        $total       = $order ? wp_strip_all_tags($order->get_formatted_order_total()) : '';
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        $lines   = [];
        $lines[] = sprintf(esc_html__( 'הזמנה #%1$s באתר %2$s ממתינה לאישור שלך.', 'tapin' ), $orderNumber, $siteTitle);
        if ($customer !== '') {
            $lines[] = sprintf(esc_html__( 'שם הלקוח/ה: %s', 'tapin' ), $customer);
        }
        if ($total !== '') {
            $lines[] = sprintf(esc_html__( 'סכום העסקה: %s', 'tapin' ), $total);
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

    private function resolveCustomerName(WC_Order $order): string
    {
        $name = trim((string) $order->get_formatted_billing_full_name());
        if ($name === '') {
            $name = trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
        }

        if ($name === '') {
            $name = (string) $order->get_billing_email();
        }

        return $name;
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
