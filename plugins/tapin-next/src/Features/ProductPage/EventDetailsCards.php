<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\MetaKeys;
use Tapin\Events\Support\Time;

final class EventDetailsCards implements Service
{
    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_action('woocommerce_single_product_summary', [$this, 'render'], 25);
    }

    public function render(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        $productId = get_the_ID();
        if (!$productId) {
            return;
        }

        $eventAddress = get_post_meta($productId, MetaKeys::EVENT_ADDRESS, true);
        $eventCity    = get_post_meta($productId, MetaKeys::EVENT_CITY, true);
        $minAgeVal    = get_post_meta($productId, MetaKeys::EVENT_MIN_AGE, true);
        $minAge       = $minAgeVal !== '' ? (int) $minAgeVal : null;

        $eventTs   = Time::productEventTs((int) $productId);
        $eventDate = $eventTs ? wp_date(get_option('date_format'), $eventTs, Time::tz()) : '';
        $eventTime = $eventTs ? wp_date('H:i', $eventTs, Time::tz()) : '';

        $producerId   = (int) get_post_field('post_author', $productId);
        $producer     = $producerId > 0 ? get_userdata($producerId) : null;
        $producerName = $producer instanceof \WP_User ? (string) $producer->display_name : '';
        $producerUrl  = '';

        if ($producerId > 0) {
            if (function_exists('um_user_profile_url')) {
                $producerUrl = (string) um_user_profile_url($producerId);
            } elseif (function_exists('um_profile_url')) {
                $producerUrl = (string) um_profile_url($producerId);
            } else {
                $producerUrl = get_author_posts_url($producerId);
            }
        }

        if ($eventAddress === '' && $eventCity === '' && !$eventTs && $producerName === '') {
            return;
        }

        ?>
        <style>
            .tapin-box {
                background:#fff;
                border:1px solid #e5e7eb;
                border-radius:12px;
                padding:12px 16px;
                margin:18px 0;
                box-shadow:0 2px 10px rgba(2,6,23,.04);
            }
            .tapin-section-title {
                font-size:1rem;
                font-weight:700;
                color:#2a1a5e;
                margin:0 0 10px;
                padding-bottom:6px;
                border-bottom:1px solid #e5e7eb;
            }
            .tapin-event-details__list {
                margin:0;
                padding:0;
                list-style:none;
                font-size:.9rem;
                color:#111827;
            }
            .tapin-event-details__row {
                display:flex;
                justify-content:space-between;
                gap:8px;
                margin-bottom:6px;
            }
            .tapin-event-details__label {
                font-weight:600;
                white-space:nowrap;
            }
            .tapin-event-details__value {
                text-align:left;
                direction:ltr;
            }
            .tapin-producer-details__link {
                display:inline-block;
                font-weight:600;
                color:#2563eb;
                text-decoration:none;
            }
            .tapin-producer-details__link:hover {
                text-decoration:underline;
            }
            body.single-product.tapin-product-enhanced .entry-summary .tapin-event-details__list,
            body.single-product.tapin-product-enhanced .entry-summary .tapin-event-details__label,
            body.single-product.tapin-product-enhanced .entry-summary .tapin-event-details__value,
            body.single-product.tapin-product-enhanced .entry-summary .tapin-producer-details__link {
                color:#111827;
            }
        </style>
        <div class="tapin-box tapin-event-details">
            <h3 class="tapin-section-title">פרטי אירוע</h3>
            <ul class="tapin-event-details__list">
                <?php if ($eventAddress !== ''): ?>
                    <li class="tapin-event-details__row">
                        <span class="tapin-event-details__label">כתובת אירוע</span>
                        <span class="tapin-event-details__value"><?php echo esc_html($eventAddress); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($eventCity !== ''): ?>
                    <li class="tapin-event-details__row">
                        <span class="tapin-event-details__label">עיר</span>
                        <span class="tapin-event-details__value"><?php echo esc_html($eventCity); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($eventDate !== ''): ?>
                    <li class="tapin-event-details__row">
                        <span class="tapin-event-details__label">תאריך האירוע</span>
                        <span class="tapin-event-details__value"><?php echo esc_html($eventDate); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($eventTime !== ''): ?>
                    <li class="tapin-event-details__row">
                        <span class="tapin-event-details__label">שעת פתיחת שערים</span>
                        <span class="tapin-event-details__value"><?php echo esc_html($eventTime); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($minAge !== null): ?>
                    <li class="tapin-event-details__row">
                        <span class="tapin-event-details__label">גיל מינימלי</span>
                        <span class="tapin-event-details__value"><?php echo esc_html((string) $minAge); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php if ($producerName !== ''): ?>
            <div class="tapin-box tapin-producer-details">
                <h3 class="tapin-section-title">פרטי המפיק</h3>
                <?php if ($producerUrl): ?>
                    <a class="tapin-producer-details__link" href="<?php echo esc_url($producerUrl); ?>">
                        <?php echo esc_html($producerName); ?>
                    </a>
                <?php else: ?>
                    <span class="tapin-producer-details__link">
                        <?php echo esc_html($producerName); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif;
    }
}
