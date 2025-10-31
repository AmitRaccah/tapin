<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\Time;

final class SaleWindowsCards implements Service
{
    public function register(): void
    {
        add_action('woocommerce_single_product_summary', [$this, 'render'], 12);
    }

    public function render(): void
    {
        global $product;
        if (!$product) {
            return;
        }

        $pid         = $product->get_id();
        $ticketTypes = TicketTypesRepository::get($pid);
        $windows     = SaleWindowsRepository::get($pid, $ticketTypes);
        if ($windows === []) {
            return;
        }

        $typeIndex = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type) || empty($type['id'])) {
                continue;
            }

            $typeIndex[$type['id']] = [
                'name'       => (string) ($type['name'] ?? $type['id']),
                'base_price' => isset($type['base_price']) ? (float) $type['base_price'] : 0.0,
            ];
        }

        $now     = time();
        $eventTs = Time::productEventTs($pid);

        echo '<style>' . Assets::saleWindowsCss() . '</style>';
        echo '<div class="tapin-pw"><div class="tapin-pw__title">' . esc_html__('חלונות תמחור כרטיסים', 'tapin') . '</div><div class="tapin-pw__grid">';

        foreach ($windows as $window) {
            $start = (int) ($window['start'] ?? 0);
            $end   = (int) ($window['end'] ?? 0);
            if (!$end && $eventTs) {
                $end = $eventTs;
            }

            $state = 'upcoming';
            if ($start <= $now && ($end === 0 || $now < $end)) {
                $state = 'current';
            } elseif ($end && $now >= $end) {
                $state = 'past';
            }

            $startStr = $start ? Time::fmtLocal($start) : esc_html__('תאריך התחלה לא הוגדר', 'tapin');
            $endStr   = $end ? Time::fmtLocal($end) : esc_html__('עד לתאריך האירוע', 'tapin');
            $badge    = match ($state) {
                'current'  => esc_html__('במכירה', 'tapin'),
                'upcoming' => esc_html__('בקרוב', 'tapin'),
                default    => esc_html__('הסתיים', 'tapin'),
            };

            $pricesMap   = is_array($window['prices'] ?? null) ? $window['prices'] : [];
            $ticketsHtml = '';
            $lowest      = null;

            foreach ($typeIndex as $typeId => $info) {
                $price = isset($pricesMap[$typeId]) ? (float) $pricesMap[$typeId] : (float) $info['base_price'];
                if ($price > 0 && ($lowest === null || $price < $lowest)) {
                    $lowest = $price;
                }

                $priceHtml = $price > 0 ? wc_price($price) : '&mdash;';
                $ticketsHtml .= '<div class="tapin-pw-card__ticket">'
                    . '<span class="tapin-pw-card__ticket-name">' . esc_html($info['name']) . '</span>'
                    . '<span class="tapin-pw-card__ticket-price">' . $priceHtml . '</span>'
                    . '</div>';
            }

            if ($lowest === null) {
                $lowest = 0.0;
            }

            echo '<div class="tapin-pw-card tapin-pw-card--' . esc_attr($state) . '">';
            echo '<div class="tapin-pw-card__row">'
                . '<span class="tapin-pw-card__price">' . ($lowest > 0 ? wc_price($lowest) : '&mdash;') . '</span>'
                . '<span class="tapin-pw-card__badge">' . esc_html($badge) . '</span>'
                . '</div>';
            echo '<div class="tapin-pw-card__dates">'
                . esc_html__('מתחיל:', 'tapin') . ' ' . esc_html($startStr)
                . '<br>' . esc_html__('מסתיים:', 'tapin') . ' ' . esc_html($endStr)
                . '</div>';
            if ($ticketsHtml !== '') {
                echo '<div class="tapin-pw-card__tickets">' . $ticketsHtml . '</div>';
            }
            echo '</div>';
        }

        echo '</div><div class="tapin-pw__hint">' . esc_html__('מחירי הכרטיסים מתעדכנים אוטומטית לפי החלון הפעיל. בחרו את סוג הכרטיס והמועד שנוחים לכם.', 'tapin') . '</div></div>';
    }
}

