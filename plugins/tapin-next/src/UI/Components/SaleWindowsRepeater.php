<?php

namespace Tapin\Events\UI\Components;

use Tapin\Events\Support\Assets;
use Tapin\Events\Support\Time;

final class SaleWindowsRepeater
{
    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @param array<int,array<string,mixed>> $windows
     */
    public static function render(array $windows = [], string $namePrefix = 'sale_w', array $ticketTypes = []): void
    {
        $fmt = static fn($ts) => Time::tsToLocalInput((int) $ts);

        $types = [];
        foreach ($ticketTypes as $type) {
            if (!is_array($type) || empty($type['id'])) {
                continue;
            }
            $types[] = [
                'id'   => (string) $type['id'],
                'name' => (string) ($type['name'] ?? $type['id']),
            ];
        }

        if ($types === []) {
            $types[] = ['id' => 'general', 'name' => 'כרטיס רגיל'];
        }

        if ($windows === []) {
            $windows[] = ['start' => 0, 'end' => 0, 'prices' => []];
        }

        $typesJson = wp_json_encode($types);
        if (!is_string($typesJson)) {
            $typesJson = '[]';
        }
        ?>
        <style><?php echo Assets::repeaterCss(); ?></style>
        <div class="tapin-form-row">
            <label>חלונות מחירים לפי תאריכים</label>
            <div
                class="tapin-sale-w"
                data-prefix="<?php echo esc_attr($namePrefix); ?>"
                data-ticket-types="<?php echo esc_attr($typesJson); ?>"
            >
                <div class="tapin-sale-w__rows" data-sale-rows>
                    <?php foreach ($windows as $w):
                        $start  = $fmt($w['start'] ?? 0);
                        $end    = $fmt($w['end'] ?? 0);
                        $prices = is_array($w['prices'] ?? null) ? $w['prices'] : [];
                    ?>
                    <div class="tapin-sale-w__row" data-sale-row>
                        <div class="tapin-sale-w__dates">
                            <input type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_start[]" value="<?php echo esc_attr($start); ?>">
                            <input type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_end[]"   value="<?php echo esc_attr($end); ?>">
                        </div>
                        <div class="tapin-sale-w__prices" data-price-fields>
                            <?php foreach ($types as $type):
                                $typeId = $type['id'];
                                $value  = isset($prices[$typeId]) ? (float) $prices[$typeId] : '';
                            ?>
                            <label class="tapin-sale-w__price-field" data-type-id="<?php echo esc_attr($typeId); ?>">
                                <span><?php echo esc_html($type['name']); ?></span>
                                <input
                                    type="number"
                                    data-sale-price-input
                                    data-type-id="<?php echo esc_attr($typeId); ?>"
                                    name="<?php echo esc_attr($namePrefix); ?>_price[<?php echo esc_attr($typeId); ?>][]"
                                    value="<?php echo esc_attr($value); ?>"
                                    min="0"
                                    step="0.01"
                                >
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="tapin-sale-w__remove" aria-label="הסרת חלון">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tapin-btn tapin-btn--ghost tapin-sale-w__add" data-sale-add>+ הוספת חלון מחיר</button>
                <div class="tapin-sale-w__hint">סדרו את החלונות לפי הזמן והקפידו שלא יהיו חפיפות בין התאריכים. המחיר הנמוך ביותר יוצג כל עוד החלון פעיל.</div>
            </div>
        </div>
        <script>
        (function(){
            function pad(n){ return (n<10?'0':'')+n; }
            function nextStartFrom(prevEnd){
                if(!prevEnd) return '';
                var d = new Date(prevEnd);
                if(isNaN(d.getTime())) return '';
                return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
            }
            function escapeHtml(value){
                return String(value || '').replace(/[&<>"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            }
            document.querySelectorAll('.tapin-sale-w').forEach(function(box){
                var rows   = box.querySelector('[data-sale-rows]');
                var addBtn = box.querySelector('[data-sale-add]');
                var prefix = box.getAttribute('data-prefix') || 'sale_w';
                var ticketTypes = [];
                try {
                    ticketTypes = JSON.parse(box.getAttribute('data-ticket-types') || '[]');
                } catch (e) {
                    ticketTypes = [];
                }
                if (!Array.isArray(ticketTypes) || ticketTypes.length === 0) {
                    ticketTypes = [{id:'general', name:'כרטיס רגיל'}];
                }

                function collectPrices(row){
                    var map = {};
                    row.querySelectorAll('[data-sale-price-input]').forEach(function(input){
                        var typeId = input.getAttribute('data-type-id');
                        if(typeId){
                            map[typeId] = input.value;
                        }
                    });
                    return map;
                }

                function renderPrices(row, prices){
                    var container = row.querySelector('[data-price-fields]');
                    if(!container) return;
                    var html = '';
                    ticketTypes.forEach(function(type){
                        var value = prices && typeof prices[type.id] !== 'undefined' ? prices[type.id] : '';
                        html += '<label class="tapin-sale-w__price-field" data-type-id="'+escapeHtml(type.id)+'">'+
                            '<span>'+escapeHtml(type.name || type.id)+'</span>'+
                            '<input type="number" data-sale-price-input data-type-id="'+escapeHtml(type.id)+'" name="'+escapeHtml(prefix)+'_price['+escapeHtml(type.id)+'][]" value="'+escapeHtml(value)+'" min="0" step="0.01">'+
                        '</label>';
                    });
                    container.innerHTML = html;
                }

                function createRow(defaults){
                    defaults = defaults || {};
                    var row = document.createElement('div');
                    row.className = 'tapin-sale-w__row';
                    row.setAttribute('data-sale-row', '1');
                    var startVal = defaults.start || '';
                    var endVal   = defaults.end || '';
                    row.innerHTML =
                        '<div class="tapin-sale-w__dates">'+
                            '<input type="datetime-local" name="'+escapeHtml(prefix)+'_start[]" value="'+escapeHtml(startVal)+'">'+
                            '<input type="datetime-local" name="'+escapeHtml(prefix)+'_end[]" value="'+escapeHtml(endVal)+'">'+
                        '</div>'+
                        '<div class="tapin-sale-w__prices" data-price-fields></div>'+
                        '<button type="button" class="tapin-sale-w__remove" aria-label="הסרת חלון">&times;</button>';

                    rows.appendChild(row);
                    renderPrices(row, defaults.prices || {});
                    refreshControls();
                }

                function refreshControls(){
                    var count = rows.querySelectorAll('[data-sale-row]').length;
                    if (addBtn) {
                        if (count >= 10) {
                            addBtn.setAttribute('disabled', 'disabled');
                        } else {
                            addBtn.removeAttribute('disabled');
                        }
                    }
                    var disableRemove = count <= 1;
                    rows.querySelectorAll('.tapin-sale-w__remove').forEach(function(btn){
                        if (disableRemove) {
                            btn.setAttribute('disabled', 'disabled');
                        } else {
                            btn.removeAttribute('disabled');
                        }
                    });
                }

                rows.addEventListener('click', function(event){
                    if(event.target && event.target.classList.contains('tapin-sale-w__remove')){
                        var row = event.target.closest('[data-sale-row]');
                        if(row && rows.querySelectorAll('[data-sale-row]').length > 1){
                            row.remove();
                            refreshControls();
                        }
                    }
                });

                if (addBtn) {
                    addBtn.addEventListener('click', function(){
                        var last = rows.querySelector('[data-sale-row]:last-child');
                        var lastEnd = last ? last.querySelector('input[name="'+prefix+'_end[]"]').value : '';
                        createRow({
                            start: nextStartFrom(lastEnd),
                            end: '',
                            prices: {}
                        });
                    });
                }

                function syncTypes(newTypes){
                    if (!Array.isArray(newTypes) || newTypes.length === 0) {
                        newTypes = [{id:'general', name:'כרטיס רגיל'}];
                    }
                    ticketTypes = newTypes;
                    rows.querySelectorAll('[data-sale-row]').forEach(function(row){
                        var values = collectPrices(row);
                        renderPrices(row, values);
                    });
                }

                document.addEventListener('tapinTicketTypesChanged', function(event){
                    if (!event || !event.detail || !Array.isArray(event.detail.types)) {
                        return;
                    }
                    syncTypes(event.detail.types);
                });

                refreshControls();
            });
        })();
        </script>
        <?php
    }
}
