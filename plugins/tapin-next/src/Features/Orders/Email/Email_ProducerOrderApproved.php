<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_ProducerOrderApproved extends WC_Email
{
    public function __construct()
    {
        $this->id             = 'tapin_producer_order_approved';
        $this->title          = esc_html__( 'התראה למפיק: הזמנה אושרה', 'tapin' );
        $this->description    = esc_html__( 'נשלח למפיק לאחר שהזמנה אושרה וחיוב הלקוח הושלם.', 'tapin' );
        $this->heading        = esc_html__( 'אישרת את ההזמנה בהצלחה', 'tapin' );
        $this->subject        = esc_html__( 'הזמנה #%s אושרה בהצלחה', 'tapin' );
        $this->customer_email = false;
        $this->template_html  = '';
        $this->template_plain = '';
        $this->placeholders   = [
            '{order_number}' => '',
            '{customer_name}' => '',
            '{site_title}'   => $this->get_blogname(),
        ];

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
        $itemsCount  = $order ? $order->get_item_count() : 0;
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        ob_start();
        ?>
        <div style="direction:rtl;text-align:right;font-family:Arial,Helvetica,sans-serif;">
            <p>
                <?php
                printf(
                    esc_html__( 'הזמנה #%1$s באתר %2$s אושרה ונשלחה להמשך טיפול.', 'tapin' ),
                    esc_html($orderNumber),
                    esc_html($siteTitle)
                );
                ?>
            </p>
            <?php if ($customer !== '') : ?>
                <p><?php printf( esc_html__( 'שם הלקוח/ה: %s', 'tapin' ), esc_html($customer) ); ?></p>
            <?php endif; ?>
            <?php if ($itemsCount > 0) : ?>
                <p><?php printf( esc_html__( 'כמות פריטים בהזמנה: %d', 'tapin' ), $itemsCount ); ?></p>
            <?php endif; ?>
            <?php if ($total !== '') : ?>
                <p><?php printf( esc_html__( 'סכום שחויב: %s', 'tapin' ), esc_html($total) ); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url($orderUrl); ?>" style="color:#1d4ed8;text-decoration:none;font-weight:600;">
                    <?php esc_html_e( 'לצפייה בפרטי ההזמנה', 'tapin' ); ?>
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
        $itemsCount  = $order ? $order->get_item_count() : 0;
        $orderUrl    = $order ? $this->buildOrderLink($order) : admin_url('edit.php?post_type=shop_order');

        $lines   = [];
        $lines[] = sprintf(esc_html__( 'הזמנה #%1$s באתר %2$s אושרה ונשלחה להמשך טיפול.', 'tapin' ), $orderNumber, $siteTitle);
        if ($customer !== '') {
            $lines[] = sprintf(esc_html__( 'שם הלקוח/ה: %s', 'tapin' ), $customer);
        }
        if ($itemsCount > 0) {
            $lines[] = sprintf(esc_html__( 'כמות פריטים בהזמנה: %d', 'tapin' ), $itemsCount);
        }
        if ($total !== '') {
            $lines[] = sprintf(esc_html__( 'סכום שחויב: %s', 'tapin' ), $total);
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
