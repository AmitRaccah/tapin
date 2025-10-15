<?php
namespace Tapin\Events\Features\Orders;

use Tapin\Events\Core\Service;

final class ProducerApprovalsShortcode implements Service {
    public function register(): void { add_shortcode('producer_order_approvals', [$this,'render']); }

    public function render(): string {
        if (!is_user_logged_in()) return '<div class="woocommerce-info" style="direction:rtl;text-align:right">יש להתחבר למערכת.</div>';
        $me = wp_get_current_user();
        if (!array_intersect((array)$me->roles, ['producer','owner'])) return '<div class="woocommerce-error" style="direction:rtl;text-align:right">הדף למפיקים בלבד.</div>';
        $producer_id = get_current_user_id();

        // Normalize meta for existing orders if needed
        $awaiting_ids = wc_get_orders(['status'=>['wc-awaiting-producer'],'limit'=>200,'return'=>'ids']);
        foreach ($awaiting_ids as $oid) {
            $o = wc_get_order($oid);
            if ($o && !$o->get_meta('_tapin_producer_ids')) {
                $o->update_meta_data('_tapin_producer_ids', $this->collect($o));
                $o->save();
            }
        }

        $order_ids = wc_get_orders(['status'=>['wc-awaiting-producer'],'limit'=>200,'return'=>'ids']);
        $mine=[];
        foreach ($order_ids as $oid){
            $o = wc_get_order($oid); if(!$o) continue;
            $meta = (array)$o->get_meta('_tapin_producer_ids');
            if ($meta && in_array($producer_id, $meta, true)) { $mine[]=$oid; continue; }
            foreach ($o->get_items('line_item') as $it) {
                $pid = $it->get_product_id();
                if ($pid && (int)get_post_field('post_author', $pid) === $producer_id) { $mine[]=$oid; break; }
            }
        }

        $notice='';
        if ('POST' === $_SERVER['REQUEST_METHOD'] && !empty($_POST['tapin_pa_bulk_nonce']) && wp_verify_nonce($_POST['tapin_pa_bulk_nonce'],'tapin_pa_bulk')) {
            $approve_all = !empty($_POST['approve_all']);
            $cancel_sel  = isset($_POST['bulk_cancel']);
            $selected    = array_map('absint', (array)($_POST['order_ids'] ?? []));
            if ($approve_all) $selected = $mine;
            $ok=0; $fail=0;
            foreach (array_unique($selected) as $oid) {
                if (!in_array($oid, $mine, true)) { $fail++; continue; }
                $o = wc_get_order($oid);
                if (!$o || 'awaiting-producer' !== $o->get_status()) { $fail++; continue; }
                if ($cancel_sel) { $o->update_status('cancelled','נדחה/בוטל ע"י המפיק.'); $ok++; continue; }
                AwaitingProducerGate::captureAndApprove($o); $ok++;
            }
            if ($ok || $fail) $notice = '<div class="woocommerce-message" style="direction:rtl;text-align:right">טופלו '.$ok.' הזמנות, נכשלו '.$fail.'.</div>';
            $mine = array_values(array_diff($mine, $selected));
        }

        ob_start(); ?>
        <style>
          .tapin-pa{direction:rtl;text-align:right}
          .tapin-pa .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
          .tapin-pa .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:600}
          .tapin-pa .btn-primary{background:#16a34a;color:#fff}
          .tapin-pa .btn-danger{background:#ef4444;color:#fff}
          .tapin-pa .btn-ghost{background:#f1f5f9;color:#111827}
          .tapin-pa table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(2,6,23,.05)}
          .tapin-pa thead th{background:#f8fafc;font-weight:700;border-bottom:1px solid #e5e7eb;padding:10px;font-size:.95rem;white-space:nowrap;cursor:pointer}
          .tapin-pa tbody td{border-bottom:1px solid #f1f5f9;padding:10px;vertical-align:top}
          .tapin-pa tbody tr:last-child td{border-bottom:0}
          .tapin-pa .muted{color:#64748b;font-size:.92rem}
          .tapin-pa .select-col{width:36px;text-align:center}
        </style>
        <div class="tapin-pa">
          <?php echo $notice; ?>
          <h3>הזמנות הממתינות לאישור</h3>
          <form method="post" id="tapinBulkForm">
            <?php wp_nonce_field('tapin_pa_bulk','tapin_pa_bulk_nonce'); ?>
            <div class="toolbar">
              <button class="btn btn-primary" type="submit" name="bulk_approve">אשר נבחרים</button>
              <button class="btn btn-ghost"   type="button" id="tapinApproveAll">אשר הכל</button>
              <button class="btn btn-danger"  type="submit" name="bulk_cancel" onclick="return confirm('לבטל את ההזמנות הנבחרות?')">בטל נבחרים</button>
            </div>
            <input type="hidden" name="approve_all" id="tapinApproveAllField" value="">
            <table id="tapinOrdersTable">
              <thead>
                <tr>
                  <th class="select-col"><input type="checkbox" id="tapinSelectAll" aria-label="בחר הכל"></th>
                  <th data-sort>מס׳ הזמנה</th>
                  <th>פריטים</th>
                  <th data-sort>סכום</th>
                  <th data-sort>תאריך</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($mine):
                    foreach ($mine as $oid):
                        $o = wc_get_order($oid);
                        $items = [];
                        foreach ($o->get_items('line_item') as $it){ $items[] = esc_html($it->get_name()).' × '.(int)$it->get_quantity(); }
                        $total = wp_strip_all_tags($o->get_formatted_order_total());
                        $created = $o->get_date_created() ? $o->get_date_created()->date_i18n(get_option('date_format').' H:i') : '';
                ?>
                <tr>
                  <td class="select-col"><input type="checkbox" name="order_ids[]" value="<?php echo (int)$oid; ?>"></td>
                  <td>#<?php echo esc_html($o->get_order_number()); ?></td>
                  <td class="muted"><?php echo implode('<br>', $items); ?></td>
                  <td><?php echo esc_html($total); ?></td>
                  <td><?php echo esc_html($created); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="muted" style="text-align:center">אין כרגע הזמנות ממתינות.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </form>
        </div>
        <script>
        (function(){
          var f=document.getElementById('tapinBulkForm');
          var selAll=document.getElementById('tapinSelectAll');
          if(selAll&&f){ selAll.addEventListener('change', function(){
            f.querySelectorAll('tbody input[type="checkbox"][name="order_ids[]"]').forEach(function(cb){ cb.checked=selAll.checked; });
          });}
          var btnAll=document.getElementById('tapinApproveAll'), fld=document.getElementById('tapinApproveAllField');
          if(btnAll&&fld){ btnAll.addEventListener('click', function(){ fld.value='1'; var h=document.createElement('input'); h.type='hidden'; h.name='bulk_approve'; h.value='1'; f.appendChild(h); f.submit(); }); }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function collect(\WC_Order $order): array {
        $ids=[]; foreach ($order->get_items('line_item') as $item){
            $pid=$item->get_product_id(); if(!$pid) continue;
            $author=(int)get_post_field('post_author',$pid);
            if ($author) $ids[]=$author;
        }
        return array_values(array_unique($ids));
    }
}
