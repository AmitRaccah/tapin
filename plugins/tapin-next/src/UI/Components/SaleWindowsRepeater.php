<?php
namespace Tapin\Events\UI\Components;

use Tapin\Events\Support\Time;

final class SaleWindowsRepeater {
    public static function render(array $windows=[], string $namePrefix='sale_w'): void {
        $fmt = fn($ts)=> Time::tsToLocalInput((int)$ts);
        if (empty($windows)) $windows=[['start'=>0,'end'=>0,'price'=>'']];
        ?>
        <style>
          .tapin-sale-w{border:1px solid var(--tapin-border-color);border-radius:12px;padding:12px}
          .tapin-sale-w__row{display:grid;grid-template-columns:1fr 1fr 160px 40px;gap:10px;margin-bottom:10px}
          .tapin-sale-w__remove{width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--tapin-border-color);border-radius:8px;background:#fff;cursor:pointer}
          .tapin-sale-w__add{margin-top:10px}
          @media(max-width:640px){.tapin-sale-w__row{grid-template-columns:1fr}}
        </style>
        <div class="tapin-form-row">
          <label>חלונות הנחה (אופציונלי)</label>
          <div class="tapin-sale-w" data-prefix="<?php echo esc_attr($namePrefix); ?>">
            <div class="tapin-sale-w__rows">
              <?php foreach ($windows as $w): ?>
              <div class="tapin-sale-w__row">
                <input type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_start[]" value="<?php echo esc_attr($fmt($w['start']??0)); ?>">
                <input type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_end[]"   value="<?php echo esc_attr($fmt($w['end']??0)); ?>">
                <input type="number" step="0.01" min="0" name="<?php echo esc_attr($namePrefix); ?>_price[]" value="<?php echo esc_attr($w['price']??''); ?>" placeholder="מחיר">
                <button type="button" class="tapin-sale-w__remove" aria-label="הסר">&times;</button>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="tapin-btn tapin-btn--ghost tapin-sale-w__add">+ הוספת חלון</button>
            <div style="font-size:12px;color:var(--tapin-text-light);margin-top:6px">השארת “סיום” ריק → עד מועד האירוע.</div>
          </div>
        </div>
        <script>
        (function(){
          function pad(n){return (n<10?'0':'')+n}
          function nextStartFrom(prevEnd){
            if(!prevEnd) return '';
            var d=new Date(prevEnd); if(isNaN(d.getTime())) return '';
            return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
          }
          document.querySelectorAll('.tapin-sale-w').forEach(function(box){
            var rows = box.querySelector('.tapin-sale-w__rows');
            box.addEventListener('click', function(e){
              if(e.target.classList.contains('tapin-sale-w__add')){
                var last = rows.querySelector('.tapin-sale-w__row:last-child');
                var lastEnd = last ? last.querySelector('input[name$="_end[]"]').value : '';
                var prefix = box.getAttribute('data-prefix');
                var wrap = document.createElement('div');
                wrap.className='tapin-sale-w__row';
                wrap.innerHTML =
                  '<input type="datetime-local" name="'+prefix+'_start[]" value="'+nextStartFrom(lastEnd)+'">'+
                  '<input type="datetime-local" name="'+prefix+'_end[]" value="">'+
                  '<input type="number" step="0.01" min="0" name="'+prefix+'_price[]" value="" placeholder="מחיר">'+
                  '<button type="button" class="tapin-sale-w__remove" aria-label="הסר">&times;</button>';
                rows.appendChild(wrap);
              }
              if(e.target.classList.contains('tapin-sale-w__remove')){
                var r=e.target.closest('.tapin-sale-w__row');
                if(r && rows.children.length>1) r.remove();
              }
            });
          });
        })();
        </script>
        <?php
    }
}
