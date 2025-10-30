<?php

namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;
use Tapin\Events\UI\Components\SaleWindowsRepeater;
use Tapin\Events\UI\Components\TicketTypesEditor;

final class ProducerEventRequest implements Service
{
    public function register(): void
    {
        add_shortcode('producer_event_request', [$this, 'render']);
    }

    public function render($atts): string
    {
        $a = shortcode_atts(['redirect' => ''], $atts, 'producer_event_request');

        if (!is_user_logged_in()) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">יש להתחבר למערכת על מנת לשלוח בקשת אירוע. <a href="' . esc_url(wp_login_url(get_permalink())) . '">כניסה</a>.</div>';
        }

        $me        = wp_get_current_user();
        $isAdmin   = current_user_can('manage_woocommerce');
        $roleOk    = array_intersect((array) $me->roles, ['producer', 'owner']);
        if (!$isAdmin && empty($roleOk)) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">אין לך הרשאה לשלוח בקשת אירוע.</div>';
        }

        $titleVal = isset($_POST['tapin_title']) ? sanitize_text_field(wp_unslash($_POST['tapin_title'])) : '';
        $descVal  = isset($_POST['tapin_desc']) ? wp_kses_post(wp_unslash($_POST['tapin_desc'])) : '';
        $eventVal = isset($_POST['tapin_event_dt']) ? sanitize_text_field(wp_unslash($_POST['tapin_event_dt'])) : '';

        $ticketTypesPost = ($_SERVER['REQUEST_METHOD'] === 'POST')
            ? TicketTypesRepository::parseFromPost('ticket_type')
            : TicketTypesRepository::get(0);

        $saleWindowsPost = ($_SERVER['REQUEST_METHOD'] === 'POST')
            ? SaleWindowsRepository::parseFromPost('sale_w', $ticketTypesPost)
            : [];

        $msg = '';

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['tapin_event_nonce'])
            && wp_verify_nonce($_POST['tapin_event_nonce'], 'tapin_event_submit')
        ) {
            $eventTs = $eventVal ? Time::localStrToUtcTs($eventVal) : 0;

            $capacityTotal = 0;
            $minBasePrice  = null;
            $ticketTypeErrors = false;

            if (!empty($ticketTypesPost)) {
                foreach ($ticketTypesPost as $type) {
                    $name      = sanitize_text_field($type['name'] ?? '');
                    $basePrice = isset($type['base_price']) ? (float) $type['base_price'] : 0.0;
                    $capacity  = isset($type['capacity']) ? (int) $type['capacity'] : 0;

                    if ($name === '' || $basePrice <= 0 || $capacity <= 0) {
                        $ticketTypeErrors = true;
                        break;
                    }

                    $capacityTotal += $capacity;
                    if ($minBasePrice === null || $basePrice < $minBasePrice) {
                        $minBasePrice = $basePrice;
                    }
                }
            } else {
                $ticketTypeErrors = true;
            }

            $uniqueKey = md5(get_current_user_id() . '|' . $titleVal . '|' . $eventTs . '|' . ($minBasePrice ?? 0) . '|' . $capacityTotal);
            $hasBackgroundUpload = !empty($_FILES['tapin_bg_image']['name']);

            if (get_transient('tapin_submit_' . $uniqueKey)) {
                $msg = '<div class="tapin-notice tapin-notice--success">הבקשה כבר התקבלה ומטופלת.</div>';
            } elseif (empty($_FILES['tapin_image']['name'])) {
                $msg = '<div class="tapin-notice tapin-notice--error">יש לצרף תמונת קאבר לאירוע.</div>';
            } elseif (!$titleVal || !$descVal || !$eventVal || $ticketTypeErrors || $capacityTotal <= 0 || ($minBasePrice ?? 0) <= 0) {
                $msg = '<div class="tapin-notice tapin-notice--error">וודאו שמילאתם את כל שדות החובה, הגדרתם לפחות סוג כרטיס אחד עם מחיר ומלאי, והזנתם תאריך אירוע תקין.</div>';
            } elseif ($eventTs && $eventTs < time()) {
                $msg = '<div class="tapin-notice tapin-notice--error">תאריך האירוע חייב להיות עתידי.</div>';
            } else {
                if (!empty($saleWindowsPost) && $eventTs) {
                    foreach ($saleWindowsPost as $window) {
                        if (!empty($window['end']) && $window['end'] > $eventTs) {
                            $msg = '<div class="tapin-notice tapin-notice--error">סיום חלון המחיר אינו יכול להיות לאחר מועד האירוע.</div>';
                            break;
                        }
                    }
                }
            }

            if ($msg === '') {
                set_transient('tapin_submit_' . $uniqueKey, 1, 60);

                $pid = wp_insert_post([
                    'post_type'    => 'product',
                    'post_status'  => 'pending',
                    'post_author'  => get_current_user_id(),
                    'post_title'   => $titleVal,
                    'post_content' => $descVal,
                ], true);

                if (is_wp_error($pid)) {
                    delete_transient('tapin_submit_' . $uniqueKey);
                    $msg = '<div class="tapin-notice tapin-notice--error">אירעה שגיאה בעת יצירת האירוע: ' . esc_html($pid->get_error_message()) . '</div>';
                } else {
                    update_post_meta($pid, '_virtual', 'yes');
                    update_post_meta($pid, '_stock_status', 'instock');
                    update_post_meta($pid, 'event_date', wp_date('Y-m-d H:i:s', $eventTs, wp_timezone()));
                    wp_set_object_terms($pid, 'simple', 'product_type', false);

                    delete_post_meta($pid, '_sale_price');
                    delete_post_meta($pid, '_sale_price_dates_from');
                    delete_post_meta($pid, '_sale_price_dates_to');

                    $sanitizedTypes = TicketTypesRepository::save($pid, $ticketTypesPost);
                    $totalCapacity  = TicketTypesRepository::totalCapacity($sanitizedTypes);

                    update_post_meta($pid, '_manage_stock', 'yes');
                    update_post_meta($pid, '_stock', $totalCapacity);

                    $resolvedBase = null;
                    foreach ($sanitizedTypes as $type) {
                        $basePrice = isset($type['base_price']) ? (float) $type['base_price'] : 0.0;
                        if ($basePrice <= 0) {
                            continue;
                        }
                        if ($resolvedBase === null || $basePrice < $resolvedBase) {
                            $resolvedBase = $basePrice;
                        }
                    }

                    if ($resolvedBase !== null) {
                        $formatted = function_exists('wc_format_decimal') ? wc_format_decimal($resolvedBase) : $resolvedBase;
                        update_post_meta($pid, '_regular_price', $formatted);
                        update_post_meta($pid, '_price', $formatted);
                    }

                    SaleWindowsRepository::save($pid, $saleWindowsPost, $sanitizedTypes);

                    if (function_exists('wc_get_product')) {
                        if ($product = wc_get_product($pid)) {
                            $product->set_catalog_visibility('visible');
                            $product->save();
                        }
                    }
                    if ($pending = get_term_by('slug', 'pending-events', 'product_cat')) {
                        wp_set_object_terms($pid, [(int) $pending->term_id], 'product_cat', false);
                    }

                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $coverId = media_handle_upload('tapin_image', $pid);
                    if (is_wp_error($coverId)) {
                        wp_delete_post($pid, true);
                        delete_transient('tapin_submit_' . $uniqueKey);
                        $msg = '<div class="tapin-notice tapin-notice--error">העלאת תמונת הקאבר נכשלה: ' . esc_html($coverId->get_error_message()) . '</div>';
                    } else {
                        set_post_thumbnail($pid, $coverId);

                        if ($hasBackgroundUpload) {
                            $bgId = media_handle_upload('tapin_bg_image', $pid);
                            if (is_wp_error($bgId)) {
                                wp_delete_attachment($coverId, true);
                                wp_delete_post($pid, true);
                                delete_transient('tapin_submit_' . $uniqueKey);
                                $msg = '<div class="tapin-notice tapin-notice--error">העלאת תמונת הרקע נכשלה: ' . esc_html($bgId->get_error_message()) . '</div>';
                            } else {
                                update_post_meta($pid, MetaKeys::EVENT_BG_IMAGE, (int) $bgId);
                            }
                        }

                        if ($msg === '') {
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
        <div class="tapin-center-container" style="max-width:820px">
            <h2 class="tapin-title">פתיחת אירוע חדש</h2>
            <?php echo $msg; ?>
            <form id="tapinForm" method="post" enctype="multipart/form-data" class="tapin-card" novalidate>
                <div class="tapin-form-row">
                    <label>שם האירוע *</label>
                    <input type="text" name="tapin_title" value="<?php echo esc_attr($titleVal); ?>" required>
                </div>
                <div class="tapin-form-row">
                    <label>תיאור מלא *</label>
                    <textarea name="tapin_desc" rows="6" required><?php echo esc_textarea($descVal); ?></textarea>
                </div>
                <div class="tapin-form-row">
                    <label>תמונת קאבר *</label>
                    <input type="file" name="tapin_image" accept="image/*" required>
                </div>
                <div class="tapin-form-row">
                    <label>תמונת רקע לדף המוצר (אופציונלי)</label>
                    <input type="file" name="tapin_bg_image" accept="image/*">
                    <small style="display:block;margin-top:6px;color:#475569;font-size:.85rem;">מומלץ להעלות תמונה רוחבית באיכות גבוהה (1920px לפחות).</small>
                </div>
                <?php TicketTypesEditor::render($ticketTypesPost); ?>
                <?php SaleWindowsRepeater::render($saleWindowsPost, 'sale_w', $ticketTypesPost); ?>
                <div class="tapin-form-row">
                    <label>תאריך ושעה של האירוע *</label>
                    <input type="datetime-local" name="tapin_event_dt" value="<?php echo esc_attr($eventVal); ?>" required>
                </div>
                <?php wp_nonce_field('tapin_event_submit', 'tapin_event_nonce'); ?>
                <div class="tapin-actions">
                    <button id="tapinSubmitBtn" type="submit" class="tapin-btn tapin-btn--primary">שליחת בקשה</button>
                </div>
            </form>
        </div>
        <script>
        (function(){
            var form = document.getElementById('tapinForm');
            if(!form) return;
            form.addEventListener('submit', function(){
                var btn = document.getElementById('tapinSubmitBtn');
                if(btn){
                    btn.disabled = true;
                    btn.textContent = 'שולח...';
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
