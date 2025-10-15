<?php
namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
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

        echo '<style>
        .tapin-pw{direction:rtl;text-align:right;margin:10px 0 16px}
        .tapin-pw__title{font-weight:800;color:#2a1a5e;margin:0 0 10px;font-size:16px;text-align:center}
        .tapin-pw__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
        .tapin-pw-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:12px 14px;box-shadow:0 4px 12px rgba(2,6,23,.05)}
        .tapin-pw-card--current{box-shadow:0 0 0 2px rgba(22,163,74,.15) inset}
        .tapin-pw-card--upcoming{box-shadow:0 0 0 2px rgba(14,165,233,.12) inset}
        .tapin-pw-card--past{opacity:.7}
        .tapin-pw-card__row{display:flex;align-items:center;justify-content:space-between}
        .tapin-pw-card__price{font-weight:800;font-size:1.15rem}
        .tapin-pw-card__badge{font-size:.8rem;font-weight:700}
        .tapin-pw__hint{font-size:.8rem;color:#334155;margin-top:6px;text-align:center}
        </style>';

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
