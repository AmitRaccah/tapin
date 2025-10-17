<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Features\Admin\ProducerCenterActions;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\UI\Forms\EventFormRenderer;

final class ProducerEventsCenter implements Service {
    public function register(): void {
        add_shortcode('producer_events_center', [$this, 'render']);
    }

    public function render($atts = []): string {
        if (!is_user_logged_in()) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">יש להתחבר למערכת.</div>';
        }

        $user        = wp_get_current_user();
        $roles       = (array) $user->roles;
        $is_producer = in_array('producer', $roles, true) || in_array('owner', $roles, true);

        if (!$is_producer) {
            status_header(403);
            return '<div class="tapin-notice tapin-notice--error">הדף זמין למפיקים בלבד.</div>';
        }

        $message = ProducerCenterActions::handle();

        $pending_query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => ['pending'],
            'author'         => $user->ID,
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);
        $pending_ids = $pending_query->have_posts() ? wp_list_pluck($pending_query->posts, 'ID') : [];

        $active_query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => ['publish'],
            'author'         => $user->ID,
            'meta_key'       => MetaKeys::EVENT_DATE,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [[
                'key'     => MetaKeys::EVENT_DATE,
                'compare' => '>=',
                'value'   => wp_date('Y-m-d H:i:s', time(), wp_timezone()),
                'type'    => 'DATETIME',
            ]],
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);
        $active_ids = $active_query->have_posts() ? wp_list_pluck($active_query->posts, 'ID') : [];

        ob_start(); ?>
        <style>
            <?php echo Assets::sharedCss(); ?>
            .tapin-notice{padding:12px;border-radius:8px;margin-bottom:20px;direction:rtl;text-align:right}
            .tapin-notice--error{background:#fff4f4;border:1px solid #f3c2c2}
            .tapin-notice--success{background:#f0fff4;border:1px solid #b8e1c6}
            .tapin-notice--warning{background:#fff7ed;border:1px solid #ffd7b5}
        </style>
        <div class="tapin-center-container">
            <?php echo $message; ?>

            <h3 class="tapin-title">אירועים ממתינים שלי</h3>
            <?php if ($pending_ids): ?>
                <div class="tapin-form-grid">
                    <?php foreach ($pending_ids as $pid):
                        $thumb = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
                        if (!$thumb && function_exists('wc_placeholder_img_src')) {
                            $thumb = wc_placeholder_img_src();
                        }
                        if (!$thumb) {
                            $thumb = includes_url('images/media/default.png');
                        }
                    ?>
                    <form method="post" enctype="multipart/form-data" class="tapin-card">
                        <div class="tapin-card__header">
                            <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                            <div style="flex:1">
                                <h4 class="tapin-card__title"><?php echo esc_html(get_the_title($pid)); ?></h4>
                            </div>
                        </div>

                        <?php EventFormRenderer::renderFields($pid, ['name_prefix' => 'sale_w']); ?>

                        <div class="tapin-actions">
                            <button type="submit" name="save_pending" class="tapin-btn tapin-btn--primary">שמירה</button>
                        </div>
                        <?php wp_nonce_field('tapin_pe_action', 'tapin_pe_nonce'); ?>
                        <input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">
                    </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>אין כרגע אירועים ממתינים.</p>
            <?php endif; ?>

            <h3 class="tapin-title" style="margin-top:40px;">אירועים פעילים שלי</h3>
            <?php if ($active_ids): ?>
                <div class="tapin-form-grid">
                    <?php foreach ($active_ids as $pid):
                        $thumb = get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail');
                        if (!$thumb && function_exists('wc_placeholder_img_src')) {
                            $thumb = wc_placeholder_img_src();
                        }
                        if (!$thumb) {
                            $thumb = includes_url('images/media/default.png');
                        }
                        $request   = get_post_meta($pid, MetaKeys::EDIT_REQ, true);
                        $is_paused = get_post_meta($pid, MetaKeys::PAUSED, true) === 'yes';
                    ?>
                    <form method="post" enctype="multipart/form-data" class="tapin-card <?php echo $is_paused ? 'tapin-card--paused' : ''; ?>">
                        <div class="tapin-card__header">
                            <img class="tapin-card__thumb" src="<?php echo esc_url($thumb); ?>" alt="">
                            <div style="flex:1">
                                <h4 class="tapin-card__title">
                                    <?php echo esc_html(get_the_title($pid)); ?>
                                    <?php if ($is_paused): ?>
                                        <span class="tapin-status-badge tapin-status-badge--paused">— מכירה מושהית</span>
                                    <?php endif; ?>
                                    <?php if (!empty($request)): ?>
                                        <span class="tapin-status-badge tapin-status-badge--pending">בקשת עדכון ממתינה</span>
                                    <?php endif; ?>
                                </h4>
                            </div>
                        </div>

                        <?php EventFormRenderer::renderFields($pid, ['name_prefix' => 'sale_w']); ?>

                        <div class="tapin-actions">
                            <?php if (empty($request)): ?>
                                <button type="submit" name="request_edit" class="tapin-btn tapin-btn--primary">בקשת עדכון</button>
                            <?php else: ?>
                                <button type="submit" name="cancel_request" class="tapin-btn tapin-btn--ghost">ביטול בקשה</button>
                            <?php endif; ?>
                        </div>
                        <?php wp_nonce_field('tapin_pe_action', 'tapin_pe_nonce'); ?>
                        <input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">
                    </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>אין אירועים פעילים כרגע.</p>
            <?php endif; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        <?php
        return ob_get_clean();
    }
}
