<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class ProducerEventRequest implements Service {
    public function register(): void { add_shortcode('producer_event_request', [$this,'render']); }

    public function render($atts): string {
        $a = shortcode_atts(['redirect'=>''], $atts, 'producer_event_request');

        if (!is_user_logged_in()) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">הדף זמין למפיקים או לבעלי האתר בלבד. <a href="'.esc_url(wp_login_url(get_permalink())).'">התחבר/י</a>.</div>';
        }
        $me = wp_get_current_user();
        $is_admin = current_user_can('manage_woocommerce');
        $role_ok  = array_intersect((array)$me->roles, ['producer','owner']);
        if (!$is_admin && empty($role_ok)) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">אין לך הרשאה לצפות בדף זה.</div>';
        }

        $title_val = isset($_POST['tapin_title']) ? sanitize_text_field(wp_unslash($_POST['tapin_title'])) : '';
        $desc_val  = isset($_POST['tapin_desc'])  ? wp_kses_post(wp_unslash($_POST['tapin_desc'])) : '';
        $price_val = isset($_POST['tapin_price']) ? wc_format_decimal(wp_unslash($_POST['tapin_price'])) : '';
        $event_val = isset($_POST['tapin_event_dt']) ? sanitize_text_field(wp_unslash($_POST['tapin_event_dt'])) : '';
        $stock_val = isset($_POST['tapin_stock']) ? absint($_POST['tapin_stock']) : '';

        $sale_windows_post = ($_SERVER['REQUEST_METHOD'] === 'POST') ? SaleWindowsRepository::parseFromPost('sale_w') : [];
        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tapin_event_nonce']) && wp_verify_nonce($_POST['tapin_event_nonce'], 'tapin_event_submit')) {
            $event_ts = $event_val ? Time::localStrToUtcTs($event_val) : 0;
            $unique_key = md5(get_current_user_id().'|'.$title_val.'|'.$event_ts.'|'.$price_val);

            if (get_transient('tapin_submit_'.$unique_key)) {
                $msg = '<div class="tapin-notice tapin-notice--success">הטופס כבר התקבל.</div>';
            } elseif (empty($_FILES['tapin_image']['name'])) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש להעלות תמונה.</div>';
            } elseif (empty($_FILES['tapin_bg_image']['name'])) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש להעלות תמונת רקע.</div>';
            } elseif (!$title_val || !$desc_val || $price_val==='' || !$event_val || $stock_val === '' || $stock_val <= 0) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש למלא כותרת, תיאור, מחיר, כמות ותאריך/שעה.</div>';
            } elseif ($event_ts && $event_ts < time()) {
                $msg = '<div class="tapin-notice tapin-notice--error">מועד האירוע כבר עבר.</div>';
            } else {
                if (!empty($sale_windows_post) && $event_ts){
                    foreach ($sale_windows_post as $w){
                        if (!empty($w['end']) && $w['end'] > $event_ts){
                            $msg = '<div class="tapin-notice tapin-notice--error">חלון הנחה מסתיים אחרי מועד האירוע.</div>';
                            break;
                        }
                    }
                }
            }

            if (empty($msg)) {
                set_transient('tapin_submit_'.$unique_key, 1, 60);

                $pid = wp_insert_post([
                    'post_type'=>'product','post_status'=>'pending','post_author'=>get_current_user_id(),
                    'post_title'=>$title_val,'post_content'=>$desc_val,
                ], true);

                if (is_wp_error($pid)) {
                    delete_transient('tapin_submit_'.$unique_key);
                    $msg = '<div class="tapin-notice tapin-notice--error">שגיאה ביצירת אירוע: '.esc_html($pid->get_error_message()).'</div>';
                } else {
                    update_post_meta($pid, '_virtual','yes');
                    update_post_meta($pid, '_manage_stock','yes');
                    update_post_meta($pid, '_stock', $stock_val);
                    update_post_meta($pid, '_stock_status','instock');
                    update_post_meta($pid, '_regular_price',$price_val);
                    update_post_meta($pid, '_price',$price_val);
                    update_post_meta($pid, 'event_date', wp_date('Y-m-d H:i:s', $event_ts, wp_timezone()));
                    wp_set_object_terms($pid, 'simple', 'product_type', false);

                    delete_post_meta($pid,'_sale_price'); delete_post_meta($pid,'_sale_price_dates_from'); delete_post_meta($pid,'_sale_price_dates_to');
                    \Tapin\Events\Domain\SaleWindowsRepository::save($pid, $sale_windows_post);

                    if (function_exists('wc_get_product')) {
                        if ($p = wc_get_product($pid)) { $p->set_catalog_visibility('visible'); $p->save(); }
                    }
                    if ($pending = get_term_by('slug','pending-events','product_cat')) {
                        wp_set_object_terms($pid, [(int)$pending->term_id], 'product_cat', false);
                    }

                    require_once ABSPATH.'wp-admin/includes/file.php';
                    require_once ABSPATH.'wp-admin/includes/media.php';
                    require_once ABSPATH.'wp-admin/includes/image.php';
                    $att_id = media_handle_upload('tapin_image', $pid);
                    if (is_wp_error($att_id)) {
                        wp_delete_post($pid, true);
                        delete_transient('tapin_submit_'.$unique_key);
                        $msg = '<div class="tapin-notice tapin-notice--error">שגיאה בהעלאת התמונה: '.esc_html($att_id->get_error_message()).'</div>';
                    } else {
                        set_post_thumbnail($pid, $att_id);
                        $bg_id = media_handle_upload('tapin_bg_image', $pid);
                        if (is_wp_error($bg_id)) {
                            wp_delete_attachment($att_id, true);
                            wp_delete_post($pid, true);
                            delete_transient('tapin_submit_'.$unique_key);
                            $msg = '<div class="tapin-notice tapin-notice--error">שגיאה בהעלאת תמונת הרקע: '.esc_html($bg_id->get_error_message()).'</div>';
                        } else {
                            update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $bg_id);
                            $target = $a['redirect'] ? esc_url_raw($a['redirect']) : home_url('/');
                            $target = add_query_arg('tapin_thanks', '1', $target);
                            wp_safe_redirect($target);
                            exit;
                        }
                    }
                }
            }
        }

        ob_start(); ?>
        <style><?php echo Assets::sharedCss(); ?></style>
        <div class="tapin-center-container" style="max-width:780px">
          <h2 class="tapin-title">יצירת אירוע חדש</h2>
          <?php echo $msg; ?>
          <form id="tapinForm" method="post" enctype="multipart/form-data" class="tapin-card" novalidate>
            <div class="tapin-form-row"><label>כותרת האירוע *</label><input type="text" name="tapin_title" value="<?php echo esc_attr($title_val); ?>" required></div>
            <div class="tapin-form-row"><label>תיאור *</label><textarea name="tapin_desc" rows="6" required><?php echo esc_textarea($desc_val); ?></textarea></div>
            <div class="tapin-form-row"><label>תמונה *</label><input type="file" name="tapin_image" accept="image/*" required></div>
            <div class="tapin-form-row"><label>תמונת רקע לדף המכירה *</label><input type="file" name="tapin_bg_image" accept="image/*" required><small style="display:block;margin-top:6px;color:#475569;font-size:.85rem;">מומלץ להעלות תמונה לרוחב של 1920 פיקסלים לפחות כדי לשמור על חדות בכל מסך.</small></div>
            <div class="tapin-columns-2">
              <div class="tapin-form-row"><label>מחיר *</label><input type="number" name="tapin_price" step="0.01" min="0" value="<?php echo esc_attr($price_val); ?>" required></div>
              <div class="tapin-form-row"><label>כמות *</label><input type="number" name="tapin_stock" min="1" step="1" value="<?php echo esc_attr($stock_val); ?>" required></div>
            </div>
            <?php \Tapin\Events\UI\Components\SaleWindowsRepeater::render($sale_windows_post, 'sale_w'); ?>
            <div class="tapin-form-row"><label>תאריך ושעה *</label><input type="datetime-local" name="tapin_event_dt" value="<?php echo esc_attr($event_val); ?>" required></div>
            <?php wp_nonce_field('tapin_event_submit','tapin_event_nonce'); ?>
            <div class="tapin-actions"><button id="tapinSubmitBtn" type="submit" class="tapin-btn tapin-btn--primary">שליחה לאישור</button></div>
          </form>
        </div>
        <script>(function(){var f=document.getElementById('tapinForm');if(!f)return;f.addEventListener('submit',function(){var b=document.getElementById('tapinSubmitBtn');if(b){b.disabled=true;b.textContent='שולח…';}});})();</script>
        <?php
        return ob_get_clean();
    }
}
