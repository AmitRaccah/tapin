<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\Email;

use WC_Email;
use WC_Order;

final class Email_CustomerOrderPartiallyApproved extends WC_Email
{
    private const LEGACY_PRODUCER_ID = 0;

    private int $producerId = 0;

    public function __construct()
    {
        $this->id             = 'tapin_customer_order_partially_approved';
        $this->title          = esc_html__('אישור חלקי של הזמנה (Tapin)', 'tapin');
        $this->description    = esc_html__('אימייל זה נשלח ללקוח המשלם כאשר חלק מהמשתתפים אושרו וחלק לא, עם פירוט מי אושר ומי לא.', 'tapin');
        $this->customer_email = true;

        $this->template_html  = 'emails/tapin-customer-order-partially-approved.php';
        $this->template_plain = 'emails/plain/tapin-customer-order-partially-approved.php';
        $this->template_base  = trailingslashit(TAPIN_NEXT_PATH) . 'templates/';

        $this->subject = esc_html__('ההזמנה שלך ב-%s אושרה חלקית', 'tapin');
        $this->heading = esc_html__('ההזמנה שלך אושרה חלקית', 'tapin');

        $this->supports = ['wpml'];

        parent::__construct();

        add_action('tapin/events/order/producer_partial_approval', [$this, 'trigger'], 10, 2);
    }

    public function init_form_fields(): void
    {
        parent::init_form_fields();

        if (isset($this->form_fields['enabled'])) {
            $this->form_fields['enabled']['title'] = esc_html__('הפעלת אימייל', 'tapin');
            $this->form_fields['enabled']['label'] = esc_html__('שליחת הודעה כאשר ההזמנה אושרה חלקית על ידי המפיק.', 'tapin');
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

        $sentMap   = $this->normalizeSentMap($order->get_meta('_tapin_partial_approval_email_sent', true));
        $snapshot  = $this->resolvePartialSnapshot($order, $producerId);
        $sentValue = $this->resolveSentValue($sentMap, $producerId);

        if ($snapshot !== '' && $sentValue === $snapshot) {
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

        $sentMap[$producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID] = $snapshot !== ''
            ? $snapshot
            : (string) time();

        $order->update_meta_data('_tapin_partial_approval_email_sent', $sentMap);
        $order->save();

        $this->restore_locale();
    }

    public function get_content_html(): string
    {
        ob_start();

        wc_get_template(
            'emails/tapin-customer-order-partially-approved.php',
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
            'emails/plain/tapin-customer-order-partially-approved.php',
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

    private function resolvePartialSnapshot(WC_Order $order, int $producerId): string
    {
        $partialMap    = $this->normalizeProducerPartialMap($order->get_meta('_tapin_partial_approved_map', true), $producerId);
        $partialTotals = $this->normalizeProducerTotals($order->get_meta('_tapin_partial_approved_total', true), $producerId);

        $map = $partialMap[$producerId] ?? $partialMap[self::LEGACY_PRODUCER_ID] ?? [];
        $total = $partialTotals[$producerId] ?? $partialTotals[self::LEGACY_PRODUCER_ID] ?? 0.0;

        $payload = [
            'map'   => $map,
            'total' => round($total, 2),
        ];

        $encoded = wp_json_encode($payload);

        return is_string($encoded) && $encoded !== '' ? md5($encoded) : '';
    }

    /**
     * @param mixed $raw
     * @return array<int,array<int,int>>
     */
    private function normalizeProducerPartialMap($raw, ?int $producerId = null): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $hasNested = false;
        foreach ($raw as $value) {
            if (is_array($value)) {
                $hasNested = true;
                break;
            }
        }

        if ($hasNested) {
            $result = [];
            foreach ($raw as $producerKey => $map) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (!is_array($map)) {
                    continue;
                }
                $clean = [];
                foreach ($map as $itemId => $count) {
                    $itemKey  = (int) $itemId;
                    $intCount = (int) $count;
                    if ($itemKey <= 0 || $intCount <= 0) {
                        continue;
                    }
                    $clean[$itemKey] = $intCount;
                }
                if ($clean !== []) {
                    $result[$pid] = $clean;
                }
            }

            return $result;
        }

        $clean = [];
        foreach ($raw as $itemId => $count) {
            $itemKey  = (int) $itemId;
            $intCount = (int) $count;
            if ($itemKey <= 0 || $intCount <= 0) {
                continue;
            }
            $clean[$itemKey] = $intCount;
        }

        if ($clean === []) {
            return [];
        }

        $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;

        return [$target => $clean];
    }

    /**
     * @param mixed $raw
     * @return array<int,float>
     */
    private function normalizeProducerTotals($raw, ?int $producerId = null): array
    {
        $result = [];
        if (is_array($raw)) {
            foreach ($raw as $producerKey => $value) {
                $pid = (int) $producerKey;
                if ($pid <= 0) {
                    $pid = self::LEGACY_PRODUCER_ID;
                }
                if (is_array($value)) {
                    continue;
                }
                $floatVal = max(0.0, (float) $value);
                if ($floatVal > 0.0) {
                    $result[$pid] = $floatVal;
                }
            }
        }

        if ($result !== []) {
            return $result;
        }

        if (is_numeric($raw)) {
            $target = $producerId && $producerId > 0 ? $producerId : self::LEGACY_PRODUCER_ID;
            $val    = max(0.0, (float) $raw);
            if ($val > 0.0) {
                $result[$target] = $val;
            }
        }

        return $result;
    }

    /**
     * @param mixed $raw
     * @return array<int,string>
     */
    private function normalizeSentMap($raw): array
    {
        if (!is_array($raw)) {
            $flag = (int) $raw;
            return $flag > 0 ? [self::LEGACY_PRODUCER_ID => '1'] : [];
        }

        $clean = [];
        foreach ($raw as $producerKey => $hash) {
            $pid = (int) $producerKey;
            if ($pid <= 0) {
                $pid = self::LEGACY_PRODUCER_ID;
            }
            $val = is_string($hash) ? trim($hash) : (is_numeric($hash) ? (string) $hash : '');
            if ($val === '') {
                continue;
            }
            $clean[$pid] = $val;
        }

        return $clean;
    }

    /**
     * @param array<int,string> $sentMap
     */
    private function resolveSentValue(array $sentMap, int $producerId): ?string
    {
        if ($producerId > 0 && isset($sentMap[$producerId])) {
            return $sentMap[$producerId];
        }

        if (isset($sentMap[self::LEGACY_PRODUCER_ID])) {
            return $sentMap[self::LEGACY_PRODUCER_ID];
        }

        return null;
    }
}
