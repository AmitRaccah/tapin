<?php
namespace Tapin\Events\Support;

use Tapin\Events\Core\Service;

final class ThanksNotice implements Service {
    private bool $printed = false;

    public function register(): void {
        add_action('wp_body_open', [$this, 'render']);
        add_action('wp_footer', [$this, 'render']);
    }

    public function render(): void {
        if ($this->printed || is_admin()) { return; }
        if (empty($_GET['tapin_thanks'])) { return; }
        $this->printed = true;
        $notice = sprintf(
            '<div dir="rtl" style="position:fixed;top:16px;left:50%%;transform:translateX(-50%%);z-index:9999;background:#f0fff4;border:1px solid #b8e1c6;color:#065f46;padding:12px 16px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);font-family:inherit;text-align:right">%s</div>',
            esc_html__('תודה! הבקשה שלך התקבלה בהצלחה.', 'tapin')
        );
        echo $notice;
    }
}
