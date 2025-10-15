<?php
namespace Tapin\Events\Features\Producers;

use Tapin\Events\Core\Service;

class RequestsManager implements Service {
    public function register(): void {
        add_shortcode('producer_requests_manager', [$this, 'render']);
    }

    private function status(int $uid): string {
        $s = get_user_meta($uid, 'producer_status', true);
        return $s ? (string)$s : '';
    }

    private function sync_socials(int $uid, array $vals): void {
        $map = [
            'instagram' => ['instagram','instagram_url'],
            'facebook'  => ['facebook','facebook_url'],
            'tiktok'    => ['tiktok','tiktok_url'],
            'youtube'   => ['youtube','youtube_url'],
            'whatsapp'  => ['whatsapp','whatsapp_number','whatsapp_phone','phone_whatsapp'],
        ];
        foreach ($map as $key => $targets) {
            $v = isset($vals[$key]) ? trim((string)$vals[$key]) : '';
            if ($key === 'whatsapp') $v = preg_replace('/\D+/', '', $v);
            if ($v === '') continue;
            foreach ($targets as $t) update_user_meta($uid, $t, $v);
        }
        if (!empty($vals['website'])) {
            $url = esc_url_raw($vals['website']);
            wp_update_user(['ID'=>$uid, 'user_url'=>$url]);
            update_user_meta($uid, 'website', $url);
            update_user_meta($uid, 'website_url', $url);
        }
    }

    public function render($atts = []): string {
        if (!is_user_logged_in()) {
            status_header(403);
            return '<div style="direction:rtl;text-align:right;background:#fff4f4;border:1px solid #f3c2c2;padding:12px;border-radius:8px">הדף זמין למשתמשים מחוברים בלבד. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחבר/י</a>.</div>';
        }

        $u   = wp_get_current_user();
        $uid = (int)$u->ID;
        $role_ok = array_intersect((array)$u->roles, ['producer','owner']);
        if ($role_ok) {
            return '<div style="direction:rtl;text-align:right;background:#f0fff4;border:1px solid #b8e1c6;padding:12px;border-radius:8px">כבר יש לך הרשאות מפיק.</div>';
        }

        $msg = '';
        if ('POST' === $_SERVER['REQUEST_METHOD'] && !empty($_POST['tapin_pr_nonce']) && wp_verify_nonce($_POST['tapin_pr_nonce'], 'tapin_pr')) {
            $display = sanitize_text_field($_POST['display_name'] ?? '');
            $about   = wp_kses_post($_POST['about'] ?? '');
            $insta   = sanitize_text_field($_POST['instagram'] ?? '');
            $fb      = sanitize_text_field($_POST['facebook'] ?? '');
            $wa      = sanitize_text_field($_POST['whatsapp'] ?? '');
            $site    = esc_url_raw($_POST['website'] ?? '');

            if ($display !== '') wp_update_user(['ID'=>$uid, 'display_name'=>$display]);
            update_user_meta($uid, 'producer_about', $about);
            update_user_meta($uid, 'producer_instagram', $insta);
            update_user_meta($uid, 'producer_facebook', $fb);
            update_user_meta($uid, 'producer_whatsapp', $wa);
            update_user_meta($uid, 'producer_website', $site);

            $this->sync_socials($uid, [
                'instagram'=>$insta,'facebook'=>$fb,'whatsapp'=>$wa,'website'=>$site
            ]);

            if ($this->status($uid) !== 'approved') update_user_meta($uid, 'producer_status', 'pending');
            $msg = '<div class="tapin-notice tapin-notice--success">הבקשה נשלחה ונמצאת במצב ממתין לאישור.</div>';
        }

        $status = $this->status($uid);
        $status_html = '';
        if ($status === 'pending') {
            $status_html = '<div class="tapin-notice tapin-notice--warning">בקשתך ממתינה לאישור מנהל.</div>';
        } elseif ($status === 'rejected') {
            $status_html = '<div class="tapin-notice tapin-notice--error">בקשתך נדחתה. ניתן לעדכן פרטים ולשלוח מחדש.</div>';
        }

        ob_start(); ?>
        <style>
          .tapin-pr{direction:rtl;text-align:right;max-width:780px;margin:0 auto}
          .tapin-notice{padding:12px;border-radius:8px;margin-bottom:14px}
          .tapin-notice--success{background:#f0fff4;border:1px solid #b8e1c6}
          .tapin-notice--warning{background:#fff7ed;border:1px solid #ffd7b5}
          .tapin-notice--error{background:#fff4f4;border:1px solid #f3c2c2}
          .tapin-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
          .row{margin-bottom:12px}
          .row label{display:block;font-weight:700;margin-bottom:6px}
          .row input,.row textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
          .actions{margin-top:12px}
          .btn{background:#16a34a;color:#fff;border:0;border-radius:10px;padding:12px 18px;font-weight:700;cursor:pointer}
        </style>
        <div class="tapin-pr">
          <?= $msg . $status_html ?>
          <div class="tapin-card">
            <form method="post">
              <div class="row">
                <label>שם תצוגה</label>
                <input type="text" name="display_name" value="<?= esc_attr($u->display_name) ?>">
              </div>
              <div class="row">
                <label>תיאור קצר</label>
                <textarea name="about" rows="4"><?= esc_textarea(get_user_meta($uid,'producer_about',true)) ?></textarea>
              </div>
              <div class="row">
                <label>Instagram</label>
                <input type="text" name="instagram" value="<?= esc_attr(get_user_meta($uid,'producer_instagram',true)) ?>">
              </div>
              <div class="row">
                <label>Facebook</label>
                <input type="text" name="facebook" value="<?= esc_attr(get_user_meta($uid,'producer_facebook',true)) ?>">
              </div>
              <div class="row">
                <label>WhatsApp</label>
                <input type="text" name="whatsapp" value="<?= esc_attr(get_user_meta($uid,'producer_whatsapp',true)) ?>">
              </div>
              <div class="row">
                <label>אתר</label>
                <input type="url" name="website" value="<?= esc_attr(get_user_meta($uid,'producer_website',true)) ?>">
              </div>
              <?php wp_nonce_field('tapin_pr','tapin_pr_nonce'); ?>
              <div class="actions">
                <button class="btn" type="submit">שליחת בקשה/עדכון</button>
              </div>
            </form>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
