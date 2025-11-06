<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\UI;

use Tapin\Events\Features\ProductPage\PurchaseModal\Fields\FieldDefinitionsProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Guards\ProductGuards;
use Tapin\Events\Features\ProductPage\PurchaseModal\Messaging\MessagesProvider;
use Tapin\Events\Features\ProductPage\PurchaseModal\Tickets\TicketTypeCache;

final class ModalRenderer
{
    private ProductGuards $guards;
    private TicketTypeCache $ticketTypeCache;
    private MessagesProvider $messages;
    private FieldDefinitionsProvider $fields;

    public function __construct(
        ProductGuards $guards,
        TicketTypeCache $ticketTypeCache,
        MessagesProvider $messages,
        FieldDefinitionsProvider $fields
    ) {
        $this->guards = $guards;
        $this->ticketTypeCache = $ticketTypeCache;
        $this->messages = $messages;
        $this->fields = $fields;
    }

    public function renderHiddenField(): void
    {
        if ($this->guards->isEligibleProduct()) {
            echo '<input type="hidden" name="tapin_attendees" id="tapinAttendeesField" value="">';
        }
    }

    public function renderModal(): void
    {
        if (!$this->guards->isEligibleProduct()) {
            return;
        }

        $productId = (int) get_the_ID();
        $ticketCache = $productId ? $this->ticketTypeCache->ensureTicketTypeCache($productId) : ['list' => [], 'index' => []];
        $ticketTypes = $ticketCache['list'];
        $messages = $this->messages->getModalMessages();
        $fieldDefinitions = $this->fields->getDefinitions();
        ?>
        <div id="tapinPurchaseModal" class="tapin-purchase-modal" hidden>
            <div class="tapin-purchase-modal__backdrop" data-modal-dismiss></div>
            <div class="tapin-purchase-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tapinPurchaseModalTitle">
                <button type="button" class="tapin-purchase-modal__close" data-modal-dismiss aria-label="<?php echo esc_attr($messages['close'] ?? __('סגירת חלון', 'tapin')); ?>">&times;</button>
                <h2 id="tapinPurchaseModalTitle" class="tapin-purchase-modal__title"></h2>
                <p class="tapin-purchase-modal__subtitle" data-step-text></p>
                <div class="tapin-ticket-step" data-ticket-step>
                    <div class="tapin-ticket-step__list">
                        <?php foreach ($ticketTypes as $type):
                            $typeId      = (string) ($type['id'] ?? '');
                            $typeName    = (string) ($type['name'] ?? $typeId);
                            $description = (string) ($type['description'] ?? '');
                            $price       = isset($type['price']) ? (float) $type['price'] : 0.0;
                            $available   = isset($type['available']) ? (int) $type['available'] : 0;
                            $capacity    = isset($type['capacity']) ? (int) $type['capacity'] : 0;
                            $isSoldOut   = $capacity > 0 && $available <= 0;
                            $priceHtml   = $price > 0 ? wc_price($price) : esc_html($messages['ticketStepIncluded'] ?? __('כלול', 'tapin'));
                            ?>
                            <div
                                class="tapin-ticket-card<?php echo $isSoldOut ? ' tapin-ticket-card--soldout' : ''; ?>"
                                data-ticket-card
                                data-type-id="<?php echo esc_attr($typeId); ?>"
                                data-price="<?php echo esc_attr($price); ?>"
                                data-available="<?php echo esc_attr($available); ?>"
                                data-capacity="<?php echo esc_attr($capacity); ?>"
                            >
                                <div class="tapin-ticket-card__header">
                                    <div class="tapin-ticket-card__titles">
                                        <span class="tapin-ticket-card__name"><?php echo esc_html($typeName); ?></span>
                                        <?php if ($description !== ''): ?>
                                            <span class="tapin-ticket-card__description"><?php echo esc_html($description); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="tapin-ticket-card__price"><?php echo $priceHtml; ?></span>
                                </div>
                                <div class="tapin-ticket-card__meta">
                                <?php
                                $availabilityText = $capacity > 0
                                    ? sprintf((string) ($messages['ticketStepAvailability'] ?? __('זמין: %s', 'tapin')), max(0, $available))
                                    : (string) ($messages['ticketStepNoLimit'] ?? __('ללא הגבלה', 'tapin'));
                                echo esc_html($availabilityText);
                                ?>
                                </div>
                                <div class="tapin-ticket-card__actions">
                                    <button type="button" class="tapin-ticket-card__btn" data-ticket-action="decrease" aria-label="<?php echo esc_attr($messages['ticketStepDecrease'] ?? __('הפחת כרטיס', 'tapin')); ?>">-</button>
                                    <span class="tapin-ticket-card__quantity" data-ticket-quantity>0</span>
                                    <button type="button" class="tapin-ticket-card__btn" data-ticket-action="increase" aria-label="<?php echo esc_attr($messages['ticketStepIncrease'] ?? __('הוסף כרטיס', 'tapin')); ?>">+</button>
                                </div>
                                <?php if ($isSoldOut): ?>
                                    <div class="tapin-ticket-card__soldout"><?php echo esc_html($messages['ticketStepSoldOut'] ?? __('אזל המלאי', 'tapin')); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="tapin-ticket-step__footer">
                        <div class="tapin-ticket-step__total">
                            <span class="tapin-ticket-step__total-label" data-ticket-total-label><?php echo esc_html($messages['ticketTotalLabel'] ?? __('סה״כ כרטיסים:', 'tapin')); ?></span>
                            <span class="tapin-ticket-step__total-value" data-ticket-total-count>0</span>
                        </div>
                        <p class="tapin-ticket-step__error" data-ticket-error hidden></p>
                    </div>
                </div>
                <div class="tapin-purchase-modal__form" data-form-container hidden>
                    <div class="tapin-ticket-hint" data-ticket-hint hidden></div>
                    <div
                        class="tapin-field tapin-field--select"
                        data-ticket-type-field
                        data-required-payer="true"
                        data-required-attendee="true"
                    >
                        <label for="tapinTicketTypeSelect">
                            <?php esc_html_e('סוג הכרטיס', 'tapin'); ?> <span class="tapin-required" data-required-indicator>*</span>
                        </label>
                        <select
                            id="tapinTicketTypeSelect"
                            data-ticket-type-select
                            data-field="ticket_type_select"
                        >
                        </select>
                        <p class="tapin-field__error" data-ticket-type-error></p>
                    </div>
                    <?php foreach ($fieldDefinitions as $fieldKey => $definition): ?>
                        <?php
                        $label = (string) ($definition['label'] ?? $fieldKey);
                        $type  = (string) ($definition['type'] ?? 'text');
                        $inputType = in_array($type, ['email', 'date'], true) ? $type : 'text';
                        $choices = isset($definition['choices']) && is_array($definition['choices'])
                            ? $definition['choices']
                            : [];
                        $requirements = isset($definition['required_for']) && is_array($definition['required_for'])
                            ? $definition['required_for']
                            : ['payer' => true, 'attendee' => true];
                        $payerRequired = !empty($requirements['payer']);
                        $attendeeRequired = !empty($requirements['attendee']);
                        $requiredAttr = sprintf(
                            ' data-required-payer="%s" data-required-attendee="%s"',
                            $payerRequired ? 'true' : 'false',
                            $attendeeRequired ? 'true' : 'false'
                        );
                        $starHidden = !$payerRequired && !$attendeeRequired;
                        ?>
                        <div
                            class="tapin-field<?php echo $type === 'choice' ? ' tapin-field--choice' : ''; ?>"
                            data-field-key="<?php echo esc_attr($fieldKey); ?>"
                            data-required-payer="<?php echo $payerRequired ? 'true' : 'false'; ?>"
                            data-required-attendee="<?php echo $attendeeRequired ? 'true' : 'false'; ?>"
                        >
                            <label for="tapin-field-<?php echo esc_attr($fieldKey); ?>">
                                <?php echo esc_html($label); ?> <span class="tapin-required" data-required-indicator<?php echo $starHidden ? ' hidden' : ''; ?>>*</span>
                            </label>
                            <?php if ($type === 'choice'): ?>
                                <div class="tapin-choice" data-choice-group="<?php echo esc_attr($fieldKey); ?>">
                                    <?php foreach ($choices as $choiceValue => $choiceLabel): ?>
                                        <button
                                            type="button"
                                            class="tapin-choice__option"
                                            data-choice-value="<?php echo esc_attr((string) $choiceValue); ?>"
                                        >
                                            <?php echo esc_html((string) $choiceLabel); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <input
                                    type="hidden"
                                    id="tapin-field-<?php echo esc_attr($fieldKey); ?>"
                                    data-field="<?php echo esc_attr($fieldKey); ?>"
                                    data-field-type="choice"<?php echo $requiredAttr; ?>
                                >
                            <?php else: ?>
                                <input
                                    type="<?php echo esc_attr($inputType); ?>"
                                    id="tapin-field-<?php echo esc_attr($fieldKey); ?>"
                                    data-field="<?php echo esc_attr($fieldKey); ?>"
                                    data-field-type="<?php echo esc_attr($type); ?>"<?php echo $requiredAttr; ?>
                                >
                            <?php endif; ?>
                            <p class="tapin-field__error" data-error-role="message"></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="tapin-purchase-modal__actions">
                    <button type="button" class="tapin-btn tapin-btn--ghost" data-modal-dismiss data-modal-role="cancel">ביטול</button>
                    <button type="button" class="tapin-btn tapin-btn--secondary" data-modal-action="back" hidden><?php echo esc_html($messages['back'] ?? __('חזור', 'tapin')); ?></button>
                    <button type="button" class="tapin-btn tapin-btn--primary" data-modal-action="next">הבא</button>
                </div>
            </div>
        </div>
        <?php
    }
}
