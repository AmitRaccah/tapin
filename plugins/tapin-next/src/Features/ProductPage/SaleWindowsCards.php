<?php
namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Support\Assets;
use Tapin\Events\Support\Time;

final class SaleWindowsCards implements Service {
    public function register(): void {
        add_action('woocommerce_single_product_summary', [$this,'render'], 12);
    }
    public function render(): void {
        global $product; if (!$product) return;
        $pid = $product->get_id();
        $windows = SaleWindowsRepository::get($pid);
        if (empty($windows)) return;

        $now = time();
        $eventTs = Time::productEventTs($pid);

        echo '<style>' . Assets::saleWindowsCss() . '</style>';

        echo '<div class="tapin-pw"><div class="tapin-pw__title">מחירי מכירה לפי התאריך</div><div class="tapin-pw__grid">';
        foreach ($windows as $w) {
            $s=(int)($w['start']??0); $e=(int)($w['end']??0);
            if (!$e && $eventTs) $e=$eventTs;
            $state='upcoming';
            if ($s <= $now && ($e===0 || $now < $e)) $state='current';
            elseif ($e && $now >= $e) $state='past';

            $startStr = $s ? Time::fmtLocal($s) : '—';
            $endStr   = $e ? Time::fmtLocal($e) : 'מועד האירוע';
            $badge = ($state==='current'?'מחיר נוכחי':($state==='upcoming'?'בקרוב':'עבר'));

            echo '<div class="tapin-pw-card tapin-pw-card--'.$state.'">';
            echo   '<div class="tapin-pw-card__row"><span class="tapin-pw-card__price">'.wc_price((float)$w['price']).'</span><span class="tapin-pw-card__badge">'.$badge.'</span></div>';
            echo   '<div class="tapin-pw-card__dates">מ־ '.$startStr.'<br>עד '.$endStr.'</div>';
            echo '</div>';
        }
        echo '</div><div class="tapin-pw__hint">המחיר מתעדכן אוטומטית לפי התאריך שנקבע.</div></div>';
    }
}
