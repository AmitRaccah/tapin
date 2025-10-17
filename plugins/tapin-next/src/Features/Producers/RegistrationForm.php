<?php
namespace Tapin\Events\Features\Producers;

use Tapin\Events\Support\ProducerProfiles;
use Tapin\Events\Support\Security;

final class RegistrationForm {
    private const FLASH_KEY_PREFIX = 'tapin_bp_flash_';

    public function render(bool $is_edit_mode, bool $return_markup = false): string {
        $guard = Security::loggedIn();
        if (!$guard->allowed || !$guard->user) {
            return '<div class="tapin-scope"><div class="tapin-center-container">' . $guard->message . '</div></div>';
        }

        $current = $guard->user;
        $status  = get_user_meta($current->ID, 'producer_status', true);
        $fields  = ProducerProfiles::fieldDefaults($current->ID);
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result  = $this->processSubmission($current->ID, $is_edit_mode);
            $message = $result['message'];
            $fields  = $result['fields'];
        }

        ob_start(); ?>
        <div class="tapin-scope">
            <style><?php echo ProducerProfiles::sharedCss(); ?></style>
            <div class="tapin-center-container">
                <?php echo $message; ?>

                <?php if (!$is_edit_mode && in_array('producer', (array) $current->roles, true)): ?>
                    <div class="tapin-card tapin-notice tapin-notice--success">כבר מוגדר לך פרופיל מפיק.</div>
                <?php elseif (!$is_edit_mode && $status === 'pending'): ?>
                    <div class="tapin-card tapin-notice tapin-notice--warning"><strong>הבקשה בטיפול.</strong><br>נעדכן ברגע שתאושר.</div>
                <?php elseif (!$is_edit_mode && $status === 'rejected'): ?>
                    <div class="tapin-card tapin-notice tapin-notice--error">בקשתך נדחתה. ניתן לעדכן את הפרטים ולשלוח מחדש.</div>
                <?php endif; ?>

                <?php if ($is_edit_mode || $status !== 'approved'): ?>
                <form method="post" enctype="multipart/form-data" class="tapin-card" id="tapinProducerForm">
                    <?php wp_nonce_field('tapin_producer_form', 'tapin_producer_nonce'); ?>
                    <div class="tapin-form-row">
                        <label for="p_display">שם תצוגה <span style="color:#e11d48">*</span></label>
                        <input id="p_display" type="text" name="producer_display_name" value="<?php echo esc_attr($fields['producer_display_name']); ?>" required>
                    </div>
                    <div class="tapin-form-row">
                        <label for="p_address">כתובת <span style="color:#e11d48">*</span></label>
                        <input id="p_address" type="text" name="producer_address" value="<?php echo esc_attr($fields['producer_address']); ?>" required>
                    </div>

                    <div class="tapin-form-row tapin-columns-2">
                        <div>
                            <label for="p_private">טלפון פנימי <span style="color:#e11d48">*</span></label>
                            <input id="p_private" type="text" name="producer_phone_private" value="<?php echo esc_attr($fields['producer_phone_private']); ?>" placeholder="0500000000" inputmode="tel" required>
                        </div>
                        <div>
                            <label for="p_public">טלפון לפרסום (לא חובה)</label>
                            <input id="p_public" type="text" name="producer_phone_public" value="<?php echo esc_attr($fields['producer_phone_public']); ?>" placeholder="0500000000" inputmode="tel">
                        </div>
                    </div>

                    <div class="tapin-form-row">
                        <label for="p_about">אודות <span style="color:#e11d48">*</span></label>
                        <textarea id="p_about" name="producer_about" rows="6" required><?php echo esc_textarea($fields['producer_about']); ?></textarea>
                    </div>

                    <hr style="border:0;border-top:1px solid var(--tapin-border-color);margin:24px 0;">
                    <p style="text-align:center;color:var(--tapin-text-light);margin-top:-10px;margin-bottom:20px;">מדיה ותמונות</p>

                    <div class="tapin-columns-2">
                        <div class="tapin-form-row">
                            <label for="p_cover">תמונת כותרת <?php if (!$is_edit_mode): ?><span style="color:#e11d48">*</span><?php endif; ?></label>
                            <input id="p_cover" type="file" name="producer_cover" accept="image/*" <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                        </div>
                        <div class="tapin-form-row">
                            <label for="p_avatar">תמונת פרופיל</label>
                            <input id="p_avatar" type="file" name="producer_avatar" accept="image/*">
                        </div>
                    </div>

                    <p style="text-align:center;color:var(--tapin-text-light);margin:10px 0 20px;">קישורי רשתות חברתיות (לא חובה)</p>
                    <div class="tapin-columns-2">
                        <div class="tapin-form-row">
                            <label for="p_instagram">Instagram</label>
                            <input id="p_instagram" type="text" name="producer_instagram" value="<?php echo esc_attr($fields['producer_instagram']); ?>" placeholder="@username או קישור">
                        </div>
                        <div class="tapin-form-row">
                            <label for="p_facebook">Facebook</label>
                            <input id="p_facebook" type="text" name="producer_facebook" value="<?php echo esc_attr($fields['producer_facebook']); ?>" placeholder="https://facebook.com/...">
                        </div>
                    </div>

                    <div class="tapin-columns-2">
                        <div class="tapin-form-row">
                            <label for="p_whatsapp">WhatsApp</label>
                            <input id="p_whatsapp" type="text" name="producer_whatsapp" value="<?php echo esc_attr($fields['producer_whatsapp']); ?>" placeholder="0500000000" inputmode="tel">
                        </div>
                        <div class="tapin-form-row">
                            <label for="p_website">אתר</label>
                            <input id="p_website" type="text" name="producer_website" value="<?php echo esc_attr($fields['producer_website']); ?>" placeholder="https://example.com">
                        </div>
                    </div>

                    <div class="tapin-columns-2">
                        <div class="tapin-form-row">
                            <label for="p_tiktok">TikTok</label>
                            <input id="p_tiktok" type="text" name="producer_tiktok" value="<?php echo esc_attr($fields['producer_tiktok']); ?>">
                        </div>
                        <div class="tapin-form-row">
                            <label for="p_youtube">YouTube</label>
                            <input id="p_youtube" type="text" name="producer_youtube" value="<?php echo esc_attr($fields['producer_youtube']); ?>">
                        </div>
                    </div>

                    <div class="tapin-actions">
                        <button id="tapinSubmitBtn" type="submit" class="tapin-btn tapin-btn--primary">
                            <?php echo $is_edit_mode ? 'שמירת פרופיל מפיק' : 'שליחת בקשה'; ?>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function(){
          var form=document.getElementById('tapinProducerForm');
          if(!form) return;
          var btn=document.getElementById('tapinSubmitBtn');
          form.addEventListener('submit', function(){ if(btn){ btn.disabled=true; btn.textContent='מעבד…'; }});
          ['producer_phone_public','producer_phone_private','producer_whatsapp'].forEach(function(name){
            form.querySelectorAll('input[name="'+name+'"]').forEach(function(el){
              el.addEventListener('input', function(){
                var v=this.value.replace(/\D+/g,'');
                if(this.value!==v) this.value=v;
              });
            });
          });
        })();
        </script>
        <?php
        $html = ob_get_clean();
        return $return_markup ? $html : print $html;
    }

    public function displayFlash(): void {
        if (!is_user_logged_in()) {
            return;
        }
        $flash = $this->consumeFlash(get_current_user_id());
        if ($flash) {
            echo '<div id="tapin-flash-notice" style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);background:#f0fff4;border:1px solid #b8e1c6;color:#065f46;direction:rtl;">'.$flash.'</div>';
            echo "<script>setTimeout(function(){var el=document.getElementById('tapin-flash-notice');if(el){el.style.display='none';}},5000);</script>";
        }
    }

    private function processSubmission(int $user_id, bool $is_edit_mode): array {
        if (empty($_POST['tapin_producer_nonce']) || !wp_verify_nonce($_POST['tapin_producer_nonce'], 'tapin_producer_form')) {
            return [
                'message' => Security::forbiddenMessage('הבקשה נכשלה. אנא רעננו את העמוד ונסו שוב.'),
                'fields'  => ProducerProfiles::fieldDefaults($user_id),
            ];
        }

        $display_name      = sanitize_text_field(wp_unslash($_POST['producer_display_name'] ?? ''));
        $about             = wp_kses_post(wp_unslash($_POST['producer_about'] ?? ''));
        $address           = sanitize_text_field(wp_unslash($_POST['producer_address'] ?? ''));
        $phone_pub_digits  = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_phone_public'] ?? '')));
        $phone_priv_digits = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_phone_private'] ?? '')));
        $instagram         = $this->normalizeSocialUrl(wp_unslash($_POST['producer_instagram'] ?? ''), 'instagram');
        $facebook          = $this->normalizeSocialUrl(wp_unslash($_POST['producer_facebook'] ?? ''), 'facebook');
        $whatsapp          = preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['producer_whatsapp'] ?? '')));
        $website           = $this->normalizeSocialUrl(wp_unslash($_POST['producer_website'] ?? ''), 'website');
        $tiktok            = $this->normalizeSocialUrl(wp_unslash($_POST['producer_tiktok'] ?? ''), 'tiktok');
        $youtube           = $this->normalizeSocialUrl(wp_unslash($_POST['producer_youtube'] ?? ''), 'youtube');

        $fields = [
            'producer_display_name'  => $display_name,
            'producer_about'         => $about,
            'producer_address'       => $address,
            'producer_phone_public'  => $phone_pub_digits,
            'producer_phone_private' => $phone_priv_digits,
            'producer_instagram'     => $instagram,
            'producer_facebook'      => $facebook,
            'producer_whatsapp'      => $whatsapp,
            'producer_website'       => $website,
            'producer_tiktok'        => $tiktok,
            'producer_youtube'       => $youtube,
        ];

        $has_cover_meta = (int) get_user_meta($user_id, 'producer_cover_id', true) > 0 || (bool) get_user_meta($user_id, 'cover_photo', true);
        $cover_in_post  = !empty($_FILES['producer_cover']['name']);

        if (empty($display_name) || empty($about) || empty($address) || empty($phone_priv_digits)) {
            $message = '<div class="tapin-notice tapin-notice--error">יש למלא את כל שדות החובה (*).</div>';
        } elseif ((!empty($phone_pub_digits) && strlen($phone_pub_digits) < 9) || strlen($phone_priv_digits) < 9) {
            $message = '<div class="tapin-notice tapin-notice--error">מספרי טלפון חייבים להכיל לפחות 9 ספרות.</div>';
        } elseif (!$is_edit_mode && !$has_cover_meta && !$cover_in_post) {
            $message = '<div class="tapin-notice tapin-notice--error">יש להעלות תמונת כותרת.</div>';
        } else {
            $message = $this->persistProducer($user_id, $fields, $cover_in_post, $is_edit_mode);
        }

        return [
            'message' => $message,
            'fields'  => ProducerProfiles::fieldDefaults($user_id),
        ];
    }

    private function persistProducer(int $user_id, array $fields, bool $cover_in_post, bool $is_edit_mode): string {
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        foreach ($fields as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }

        if (!empty($fields['producer_display_name'])) {
            wp_update_user(['ID' => $user_id, 'display_name' => $fields['producer_display_name']]);
        }

        ProducerProfiles::syncSocialsToUmKeys($user_id, [
            'instagram' => $fields['producer_instagram'],
            'facebook'  => $fields['producer_facebook'],
            'whatsapp'  => $fields['producer_whatsapp'],
            'tiktok'    => $fields['producer_tiktok'],
            'youtube'   => $fields['producer_youtube'],
            'website'   => $fields['producer_website'],
        ]);

        if ($cover_in_post && isset($_FILES['producer_cover']) && (int) $_FILES['producer_cover']['error'] === UPLOAD_ERR_OK) {
            $max = wp_max_upload_size();
            if (!empty($_FILES['producer_cover']['size']) && $_FILES['producer_cover']['size'] > $max) {
                return '<div class="tapin-notice tapin-notice--error">תמונת הכותרת חורגת מגודל ההעלאה (' . size_format($max) . ').</div>';
            }
            $cover_id = media_handle_upload('producer_cover', 0);
            if (is_wp_error($cover_id)) {
                return '<div class="tapin-notice tapin-notice--error">העלאת תמונת הכותרת נכשלה: ' . esc_html($cover_id->get_error_message()) . '</div>';
            }
            update_user_meta($user_id, 'producer_cover_id', (int) $cover_id);
        }

        if (!empty($_FILES['producer_avatar']['name']) && (int) $_FILES['producer_avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar_id = media_handle_upload('producer_avatar', 0);
            if (!is_wp_error($avatar_id)) {
                update_user_meta($user_id, 'producer_avatar_id', (int) $avatar_id);
            }
        }

        if (function_exists('UM')) {
            UM()->user()->remove_cache($user_id);
        }

        $status = get_user_meta($user_id, 'producer_status', true);
        if ($status !== 'approved') {
            update_user_meta($user_id, 'producer_status', 'pending');
        }

        $this->setFlash($user_id, '<div class="tapin-notice tapin-notice--success">הפרטים נשמרו בהצלחה ונשלחו לטיפול.</div>');

        if (!$is_edit_mode) {
            wp_safe_redirect($this->becomeProducerUrl());
            exit;
        }

        return '<div class="tapin-notice tapin-notice--success">הפרופיל עודכן בהצלחה.</div>';
    }

    private function normalizeSocialUrl(string $value, string $service): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $value)) {
            return esc_url_raw($value);
        }
               $value = ltrim($value, '@/');
        switch ($service) {
            case 'instagram':
                return esc_url_raw('https://instagram.com/' . $value);
            case 'facebook':
                return esc_url_raw('https://facebook.com/' . $value);
            case 'tiktok':
                return esc_url_raw('https://tiktok.com/@' . $value);
            case 'youtube':
                return esc_url_raw('https://youtube.com/' . $value);
            case 'website':
            default:
                return esc_url_raw('https://' . $value);
        }
    }

    private function consumeFlash(int $user_id): string {
        $flash = get_transient(self::FLASH_KEY_PREFIX . $user_id);
        if ($flash) {
            delete_transient(self::FLASH_KEY_PREFIX . $user_id);
            if (is_array($flash) && !empty($flash['html'])) {
                return (string) $flash['html'];
            }
            if (is_string($flash)) {
                return $flash;
            }
        }
        return '';
    }

    private function setFlash(int $user_id, string $html): void {
        set_transient(self::FLASH_KEY_PREFIX . $user_id, ['html' => $html], MINUTE_IN_SECONDS);
    }

    private function becomeProducerUrl(): string {
        if (function_exists('wc_get_account_endpoint_url')) {
            return wc_get_account_endpoint_url('become-producer');
        }
        $account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
        return add_query_arg('become-producer', '', $account);
    }
}
