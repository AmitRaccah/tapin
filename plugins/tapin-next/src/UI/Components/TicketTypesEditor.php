<?php

namespace Tapin\Events\UI\Components;

use Tapin\Events\Domain\TicketTypesRepository;
final class TicketTypesEditor
{
    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     */
    public static function render(array $ticketTypes = []): void
    {
        $types = $ticketTypes !== [] ? $ticketTypes : TicketTypesRepository::get(0);
        if ($types === []) {
            $types = [[
                'id'          => 'general',
                'name'        => 'כרטיס רגיל',
                'base_price'  => 0.0,
                'capacity'    => 0,
                'description' => '',
            ]];
        }

        $jsonTypes = wp_json_encode(array_map(static function (array $type): array {
            return [
                'id'          => (string) ($type['id'] ?? ''),
                'name'        => (string) ($type['name'] ?? ''),
                'price'       => isset($type['base_price']) ? (float) $type['base_price'] : 0.0,
                'capacity'    => isset($type['capacity']) ? (int) $type['capacity'] : 0,
                'description' => (string) ($type['description'] ?? ''),
            ];
        }, $types));

        if (!is_string($jsonTypes)) {
            $jsonTypes = '[]';
        }
        ?>
        <div class="tapin-form-row">
            <label>סוגי כרטיסים וזמינות</label>
            <div
                class="tapin-ticket-types"
                data-ticket-types
                data-ticket-types-initial="<?php echo esc_attr($jsonTypes); ?>"
            >
                <div class="tapin-ticket-types__rows" data-ticket-types-rows>
                    <?php foreach ($types as $index => $type): ?>
                        <?php
                        $id          = (string) ($type['id'] ?? '');
                        $name        = (string) ($type['name'] ?? '');
                        $price       = isset($type['base_price']) ? (float) $type['base_price'] : 0.0;
                        $capacity    = isset($type['capacity']) ? (int) $type['capacity'] : 0;
                        $description = (string) ($type['description'] ?? '');
                        ?>
                        <div class="tapin-ticket-type" data-ticket-type data-ticket-id="<?php echo esc_attr($id); ?>">
                            <div class="tapin-ticket-type__grid">
                                <div class="tapin-ticket-type__field">
                                    <label>שם סוג הכרטיס</label>
                                    <input type="text" name="ticket_type_name[]" value="<?php echo esc_attr($name); ?>" required>
                                </div>
                                <div class="tapin-ticket-type__field">
                                    <label>מחיר בסיס (לפני מבצעים)</label>
                                    <input type="number" name="ticket_type_price[]" value="<?php echo esc_attr($price); ?>" min="0" step="0.01" required>
                                </div>
                                <div class="tapin-ticket-type__field">
                                    <label>כמות זמינה</label>
                                    <input type="number" name="ticket_type_capacity[]" value="<?php echo esc_attr($capacity); ?>" min="0" step="1" required>
                                </div>
                                <div class="tapin-ticket-type__field">
                                    <label>תיאור קצר (לא חובה)</label>
                                    <input type="text" name="ticket_type_description[]" value="<?php echo esc_attr($description); ?>">
                                </div>
                            </div>
                            <input type="hidden" name="ticket_type_id[]" value="<?php echo esc_attr($id); ?>">
                            <button type="button" class="tapin-ticket-type__remove" aria-label="מחיקת סוג הכרטיס">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tapin-btn tapin-btn--ghost tapin-ticket-types__add" data-ticket-types-add>+ הוספת סוג כרטיס</button>
                <small class="tapin-ticket-types__hint">ניתן להוסיף עד 8 סוגי כרטיסים.</small>
            </div>
        </div>
        <script>
            (function(){
                var wrapper = document.querySelector('[data-ticket-types]');
                if(!wrapper) return;

                var rowsContainer = wrapper.querySelector('[data-ticket-types-rows]');
                var addButton = wrapper.querySelector('[data-ticket-types-add]');
                var maxTypes = 8;

                function uid(){
                    return 'ticket_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
                }

                function collect(){
                    var result = [];
                    rowsContainer.querySelectorAll('.tapin-ticket-type').forEach(function(row){
                        var idField = row.querySelector('input[name="ticket_type_id[]"]');
                        var nameField = row.querySelector('input[name="ticket_type_name[]"]');
                        if(!idField || !nameField) return;
                        result.push({
                            id: idField.value,
                            name: nameField.value
                        });
                    });
                    return result;
                }

                function dispatch(){
                    var detail = { types: collect() };
                    document.dispatchEvent(new CustomEvent('tapinTicketTypesChanged', { detail: detail }));
                }

                function refreshControls(){
                    var items = rowsContainer.querySelectorAll('.tapin-ticket-type').length;
                    if (addButton) {
                        if (items >= maxTypes) {
                            addButton.setAttribute('disabled', 'disabled');
                        } else {
                            addButton.removeAttribute('disabled');
                        }
                    }
                    var disableRemove = items <= 1;
                    rowsContainer.querySelectorAll('.tapin-ticket-type__remove').forEach(function(btn){
                        if (disableRemove) {
                            btn.setAttribute('disabled', 'disabled');
                        } else {
                            btn.removeAttribute('disabled');
                        }
                    });
                }

                function createRow(data){
                    var row = document.createElement('div');
                    row.className = 'tapin-ticket-type';
                    var typeId = data && data.id ? data.id : uid();
                    row.setAttribute('data-ticket-type', '1');
                    row.setAttribute('data-ticket-id', typeId);

                    var nameValue = data && data.name ? data.name : '';
                    var priceValue = data && typeof data.price !== 'undefined' ? data.price : '';
                    var capacityValue = data && typeof data.capacity !== 'undefined' ? data.capacity : '';
                    var descValue = data && data.description ? data.description : '';

                    row.innerHTML =
                        '<div class="tapin-ticket-type__grid">' +
                            '<div class="tapin-ticket-type__field">' +
                                '<label>שם סוג הכרטיס</label>' +
                                '<input type="text" name="ticket_type_name[]" value="'+escapeHtml(nameValue)+'" required>' +
                            '</div>' +
                            '<div class="tapin-ticket-type__field">' +
                                '<label>מחיר בסיס (לפני מבצעים)</label>' +
                                '<input type="number" name="ticket_type_price[]" value="'+escapeAttr(priceValue)+'" min="0" step="0.01" required>' +
                            '</div>' +
                            '<div class="tapin-ticket-type__field">' +
                                '<label>כמות זמינה</label>' +
                                '<input type="number" name="ticket_type_capacity[]" value="'+escapeAttr(capacityValue)+'" min="0" step="1" required>' +
                            '</div>' +
                            '<div class="tapin-ticket-type__field">' +
                                '<label>תיאור קצר (לא חובה)</label>' +
                                '<input type="text" name="ticket_type_description[]" value="'+escapeHtml(descValue)+'">' +
                            '</div>' +
                        '</div>' +
                        '<input type="hidden" name="ticket_type_id[]" value="'+escapeAttr(typeId)+'">' +
                        '<button type="button" class="tapin-ticket-type__remove" aria-label="מחיקת סוג הכרטיס">&times;</button>';

                    rowsContainer.appendChild(row);
                    dispatch();
                    refreshControls();
                }

                function escapeHtml(value){
                    return String(value || '').replace(/[&<>"']/g, function(c){
                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                    });
                }

                function escapeAttr(value){
                    return escapeHtml(value);
                }

                rowsContainer.addEventListener('click', function(event){
                    if(event.target && event.target.classList.contains('tapin-ticket-type__remove')){
                        var row = event.target.closest('.tapin-ticket-type');
                        if(!row) return;
                        if (rowsContainer.querySelectorAll('.tapin-ticket-type').length <= 1) {
                            return;
                        }
                        row.remove();
                        dispatch();
                        refreshControls();
                    }
                });

                rowsContainer.addEventListener('input', function(event){
                    if(event.target && event.target.name === 'ticket_type_name[]'){
                        dispatch();
                    }
                });

                if (addButton) {
                    addButton.addEventListener('click', function(){
                        if (rowsContainer.querySelectorAll('.tapin-ticket-type').length >= maxTypes) {
                            return;
                        }
                        createRow();
                    });
                }

                var initial = [];
                try {
                    initial = JSON.parse(wrapper.getAttribute('data-ticket-types-initial') || '[]');
                } catch (e) {
                    initial = [];
                }

                if (rowsContainer.children.length === 0) {
                    initial.forEach(function(item){
                        createRow(item);
                    });
                }

                refreshControls();
                dispatch();
            })();
        </script>
        <?php
    }
}
