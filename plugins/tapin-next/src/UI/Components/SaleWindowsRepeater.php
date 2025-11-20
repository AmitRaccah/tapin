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
                    <?php foreach ($windows as $idx => $w):
                        $start  = $fmt($w['start'] ?? 0);
                        $end    = $fmt($w['end'] ?? 0);
                        $prices = is_array($w['prices'] ?? null) ? $w['prices'] : [];
                        $lockStart = $idx > 0;
                        $startClasses = 'tapin-sale-w__start' . ($lockStart ? ' tapin-sale-w__start--locked' : '');
                        $startExtra   = $lockStart ? ' readonly data-locked-start="1" tabindex="-1"' : '';
                    ?>
                    <div class="tapin-sale-w__row" data-sale-row>
                        <div class="tapin-sale-w__dates">
                            <input class="<?php echo esc_attr($startClasses); ?>" type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_start[]" value="<?php echo esc_attr($start); ?>"<?php echo $startExtra; ?>>
                            <input class="tapin-sale-w__end" type="datetime-local" name="<?php echo esc_attr($namePrefix); ?>_end[]"   value="<?php echo esc_attr($end); ?>">
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

                function getStartInput(row){
                    return row ? row.querySelector('input[name="'+prefix+'_start[]"]') : null;
                }

                function getEndInput(row){
                    return row ? row.querySelector('input[name="'+prefix+'_end[]"]') : null;
                }

                function isRowComplete(row){
                    if(!row) return false;
                    var startInput = getStartInput(row);
                    var endInput   = getEndInput(row);
                    if(!startInput || !endInput || !startInput.value || !endInput.value){
                        return false;
                    }
                    var allPricesFilled = true;
                    row.querySelectorAll('[data-sale-price-input]').forEach(function(input){
                        if(!input.value){
                            allPricesFilled = false;
                        }
                    });
                    return allPricesFilled;
                }

                function applyStartLocking(rowEls){
                    rowEls = rowEls || Array.from(rows.querySelectorAll('[data-sale-row]'));
                    rowEls.forEach(function(row, idx){
                        var startInput = getStartInput(row);
                        if(!startInput) {
                            return;
                        }
                        if(idx === 0){
                            startInput.readOnly = false;
                            startInput.removeAttribute('readonly');
                            startInput.removeAttribute('data-locked-start');
                            startInput.classList.remove('tapin-sale-w__start--locked');
                            startInput.removeAttribute('tabindex');
                        } else {
                            var prevEndInput = getEndInput(rowEls[idx - 1]);
                            var prevEndVal = prevEndInput ? prevEndInput.value : '';
                            startInput.value = nextStartFrom(prevEndVal) || '';
                            startInput.readOnly = true;
                            startInput.setAttribute('readonly', 'readonly');
                            startInput.setAttribute('data-locked-start', '1');
                            startInput.classList.add('tapin-sale-w__start--locked');
                            startInput.setAttribute('tabindex', '-1');
                        }
                    });
                }

                function createRow(defaults, options){
                    defaults = defaults || {};
                    options = options || {};
                    var lockStart = !!options.lockStart;
                    var lockFrom  = options.lockFrom || '';
                    var row = document.createElement('div');
                    row.className = 'tapin-sale-w__row';
                    row.setAttribute('data-sale-row', '1');
                    var startVal = defaults.start || '';
                    if(lockStart){
                        var source = lockFrom || startVal || '';
                        startVal = nextStartFrom(source) || '';
                    }
                    var endVal   = defaults.end || '';
                    row.innerHTML =
                        '<div class="tapin-sale-w__dates">'+
                            '<input type="datetime-local" class="tapin-sale-w__start'+(lockStart?' tapin-sale-w__start--locked':'')+'" name="'+escapeHtml(prefix)+'_start[]" value="'+escapeHtml(startVal)+'"'+(lockStart?' readonly data-locked-start="1" tabindex="-1"':'')+'>'+                            '<input type="datetime-local" class="tapin-sale-w__end" name="'+escapeHtml(prefix)+'_end[]" value="'+escapeHtml(endVal)+'">'+
                        '</div>'+
                        '<div class="tapin-sale-w__prices" data-price-fields></div>'+
                        '<button type="button" class="tapin-sale-w__remove" aria-label="?"???"?x ?-?????">&times;</button>';

                    rows.appendChild(row);
                    renderPrices(row, defaults.prices || {});
                    refreshControls();
                }

                function refreshControls(){
                    var rowEls = Array.from(rows.querySelectorAll('[data-sale-row]'));
                    applyStartLocking(rowEls);
                    var count = rowEls.length;
                    if (addBtn) {
                        var disableAdd = count >= 10;
                        if (!disableAdd) {
                            var lastRow = rowEls[rowEls.length - 1] || null;
                            disableAdd = !isRowComplete(lastRow);
                        }
                        if (disableAdd) {
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
                rows.addEventListener('input', function(event){
                    var target = event.target;
                    if(!target){
                        return;
                    }
                    if (target.matches('input[name="'+prefix+'_end[]"]')) {
                        var row = target.closest('[data-sale-row]');
                        var nextRow = row ? row.nextElementSibling : null;
                        if(nextRow){
                            var nextStart = getStartInput(nextRow);
                            if(nextStart && nextStart.hasAttribute('data-locked-start')){
                                nextStart.value = nextStartFrom(target.value) || '';
                            }
                        }
                    }
                    if (
                        target.matches('input[name="'+prefix+'_start[]"]') ||
                        target.matches('input[name="'+prefix+'_end[]"]') ||
                        target.hasAttribute('data-sale-price-input')
                    ) {
                        refreshControls();
                    }
                });



                if (addBtn) {
                    addBtn.addEventListener('click', function(){
                        var rowsList = Array.from(rows.querySelectorAll('[data-sale-row]'));
                        if (rowsList.length >= 10) {
                            return;
                        }
                        var last = rowsList[rowsList.length - 1] || null;
                        if (!isRowComplete(last)) {
                            return;
                        }
                        var lastEndInput = getEndInput(last);
                        var lastEnd = lastEndInput ? lastEndInput.value : '';
                        createRow({
                            end: '',
                            prices: {}
                        }, {
                            lockStart: true,
                            lockFrom: lastEnd
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
                    refreshControls();
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
