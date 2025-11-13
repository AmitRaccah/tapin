<?php
namespace Tapin\Events\Features\Shortcodes;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;

final class ProducerEventsGrid implements Service {
    public function register(): void {
        add_shortcode('producer_events_grid', [$this, 'render']);
    }

    private function resolveProducerId(): int {
        $producer_id = 0;
        if (function_exists('um_profile_id')) {
            $producer_id = (int) um_profile_id();
        }
        if (!$producer_id && is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user && array_intersect((array) $user->roles, ['producer', 'owner'])) {
                $producer_id = (int) $user->ID;
            }
        }
        return $producer_id;
    }

    private function renderCard(int $post_id): string {
        if (!$post_id || get_post_type($post_id) !== 'product') {
            return '';
        }
        $thumb = get_the_post_thumbnail_url($post_id, 'medium');
        if (!$thumb && function_exists('wc_placeholder_img_src')) {
            $thumb = wc_placeholder_img_src();
        }
        if (!$thumb) {
            $thumb = includes_url('images/media/default.png');
        }

        $meta_key  = MetaKeys::EVENT_DATE;
        $raw_date  = get_post_meta($post_id, $meta_key, true);
        $formatted = $raw_date ? date_i18n('j M, Y', strtotime($raw_date)) : '';

        ob_start(); ?>
        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="tapin-event-card" title="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>" class="tapin-event-card__image">
            <div class="tapin-event-card__content">
                <h4 class="tapin-event-card__title"><?php echo esc_html(get_the_title($post_id)); ?></h4>
                <?php if ($formatted): ?>
                <p class="tapin-event-card__date">
                    <svg class="tapin-event-card__date-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><?php echo esc_html($formatted); ?></span>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }

    public function render($atts): string {
        $producer_id = $this->resolveProducerId();
        if (!$producer_id) {
            return '';
        }

        $profile_user = get_userdata($producer_id);
        if (!$profile_user) {
            return '';
        }
        if (!array_intersect((array) $profile_user->roles, ['producer', 'owner'])) {
            return '';
        }

        $meta_key = MetaKeys::EVENT_DATE;
        $now      = current_time('mysql');

        $active = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'author'         => $producer_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [[
                'key'     => $meta_key,
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ]],
            'no_found_rows'  => true,
        ]);

        $past = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'author'         => $producer_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [[
                'key'     => $meta_key,
                'value'   => $now,
                'compare' => '<',
                'type'    => 'DATETIME',
            ]],
            'no_found_rows'  => true,
        ]);

        ob_start(); ?>
        <style>
            .tapin-events-wrapper { direction: rtl; text-align: right; --tapin-primary-color:#2a1a5e; --tapin-border-color:#e5e7eb; --tapin-ghost-bg:#f1f5f9; --tapin-text-dark:#1f2937; --tapin-text-light:#334155; --tapin-radius-md:12px; --tapin-card-shadow:0 4px 12px rgba(2,6,23,.05); }
            .tapin-box { background:#fff; border:1px solid var(--tapin-border-color); border-radius:var(--tapin-radius-md); padding:12px; margin:18px 0; box-shadow:0 2px 10px rgba(2,6,23,.04); }
            .tapin-section-title { font-size:1rem; font-weight:700; color:var(--tapin-primary-color); margin:0 0 10px; padding-bottom:6px; border-bottom:1px solid var(--tapin-border-color); }
            .tapin-events-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
            .tapin-event-card { display:flex; flex-direction:column; text-decoration:none; background:#fff; border:1px solid var(--tapin-border-color); border-radius:10px; overflow:hidden; box-shadow:var(--tapin-card-shadow); transition:transform .2s ease-out, box-shadow .2s ease-out; }
            .tapin-event-card:hover { transform:translateY(-3px); box-shadow:0 6px 16px rgba(2,6,23,.06); }
            .tapin-event-card__image { width:100%; height:72px; object-fit:cover; display:block; background-color:var(--tapin-ghost-bg); }
            .tapin-event-card__content { padding:8px; flex-grow:1; display:flex; flex-direction:column; }
            .tapin-event-card__title { font-size:.8rem; font-weight:600; color:var(--tapin-text-dark); margin:0 0 6px; line-height:1.35; }
            .tapin-event-card__date { font-size:.72rem; color:var(--tapin-text-light); margin:0; margin-top:auto; display:flex; align-items:center; gap:4px; }
            .tapin-event-card__date-icon { width:.9em; height:.9em; opacity:.7; flex-shrink:0; }
            .tapin-no-events { padding:12px; background-color:var(--tapin-ghost-bg); border-radius:10px; color:var(--tapin-text-light); text-align:center; font-size:.85rem; }
            @media (max-width:480px) {
                .tapin-events-grid { grid-template-columns:repeat(2,1fr); gap:8px; }
                .tapin-event-card__title { font-size:.78rem; }
                .tapin-event-card__date { font-size:.7rem; }
                .tapin-event-card__image { height:66px; }
                .tapin-box { padding:10px; }
            }
        </style>
        <div class="tapin-events-wrapper">
            <div class="tapin-box">
                <h3 class="tapin-section-title">אירועים פעילים</h3>
                <?php if ($active->have_posts()): ?>
                    <div class="tapin-events-grid">
                        <?php while ($active->have_posts()): $active->the_post();
                            echo $this->renderCard(get_the_ID());
                        endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="tapin-no-events">אין אירועים פעילים כרגע.</p>
                <?php endif; wp_reset_postdata(); ?>
            </div>
            <div class="tapin-box">
                <h3 class="tapin-section-title">אירועים שהסתיימו</h3>
                <?php if ($past->have_posts()): ?>
                    <div class="tapin-events-grid">
                        <?php while ($past->have_posts()): $past->the_post();
                            echo $this->renderCard(get_the_ID());
                        endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="tapin-no-events">אין אירועים קודמים להצגה.</p>
                <?php endif; wp_reset_postdata(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
