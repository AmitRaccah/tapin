<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
use Tapin\Events\Support\ProductAvailability;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class PurchaseDetailsModal implements Service
{
    private const SCRIPT_HANDLE        = 'tapin-purchase-modal';
    private const STYLE_HANDLE         = 'tapin-purchase-modal';
    private const SESSION_KEY_PENDING  = 'tapin_pending_checkout';

    /** @var array<int, array<string,string>> */
    private array $pendingAttendees = [];
    /** @var array<int,array<string,mixed>> */
    private array $attendeeQueue = [];
    private bool $processingSplitAdd = false;
    private bool $redirectNextAdd   = false;
    /** @var array<int,array{list:array<int,array<string,mixed>>,index:array<string,array<string,mixed>>> */
    private array $ticketTypeCache  = [];
    /** @var array<string,array<string,mixed>> */
    private array $currentTicketTypeIndex = [];
    /** @var array<string,string>|null */
    private ?array $modalMessages = null;

    public function register(): void
    {
        if (!function_exists('is_product')) {
            return;
        }

        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'filterButtonText'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderHiddenField'], 5);
        add_action('woocommerce_after_add_to_cart_form', [$this, 'renderModal'], 10);

        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateSubmission'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'attachCartItemData'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restoreCartItemFromSession'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'displayCartItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'storeOrderItemMeta'], 10, 4);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hideOrderItemMeta'], 10, 1);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'filterFormattedMeta'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyAttendeePricing'], 20);

        add_filter('woocommerce_add_to_cart_redirect', [$this, 'maybeRedirectToCheckout'], 10, 2);
        add_action('init', [$this, 'maybeResumePendingCheckout'], 20);
    }

    public function filterButtonText(string $text, $product): string
    {
        if (!is_product() || !$product instanceof WC_Product) {
            return $text;
        }

        if (!$product->is_purchasable() || !$product->is_type('simple')) {
            return $text;
        }

        return __('בחירת כרטיסים', 'tapin');
    }

    public function enqueueAssets(): void
    {
        if (!$this->isEligibleProduct()) {
            return;
        }

        $assetsDirPath = plugin_dir_path(__FILE__) . 'assets/';
        $assetsDirUrl  = plugin_dir_url(__FILE__) . 'assets/';

        wp_enqueue_style(
            self::STYLE_HANDLE,
            $assetsDirUrl . 'purchase-modal.css',
            [],
            $this->assetVersion($assetsDirPath . 'purchase-modal.css')
        );

        $productId   = (int) get_the_ID();
        $ticketCache = $productId ? $this->ensureTicketTypeCache($productId) : ['list' => [], 'index' => []];

        $modalData = [
            'prefill'     => $this->getPrefillData(),
            'ticketTypes' => $ticketCache['list'],
            'messages'    => $this->getModalMessages(),
            'fields'      => $this->getFieldDefinitions(),
        ];

        $scriptBaseHandle = self::SCRIPT_HANDLE;
        $scriptBasePath   = $assetsDirPath . 'js/tapin-purchase/';
        $scriptBaseUrl    = $assetsDirUrl . 'js/tapin-purchase/';

        $utilsHandle    = $scriptBaseHandle . '-utils';
        $messagesHandle = $scriptBaseHandle . '-messages';
        $domHandle      = $scriptBaseHandle . '-dom';
        $ticketsHandle  = $scriptBaseHandle . '-tickets';
        $planHandle     = $scriptBaseHandle . '-plan';
        $formHandle     = $scriptBaseHandle . '-form';
        $modalHandle    = $scriptBaseHandle . '-modal';
        $indexHandle    = $scriptBaseHandle . '-index';

        wp_register_script(
            $utilsHandle,
            $scriptBaseUrl . 'tapin.utils.js',
            [],
            $this->assetVersion($scriptBasePath . 'tapin.utils.js'),
            true
        );

        wp_add_inline_script(
            $utilsHandle,
            'window.TapinPurchaseModalData = ' . wp_json_encode($modalData) . ';',
            'before'
        );

        wp_register_script(
            $messagesHandle,
            $scriptBaseUrl . 'tapin.messages.js',
            [$utilsHandle],
            $this->assetVersion($scriptBasePath . 'tapin.messages.js'),
            true
        );

        wp_register_script(
            $domHandle,
            $scriptBaseUrl . 'tapin.dom.js',
            [$messagesHandle],
            $this->assetVersion($scriptBasePath . 'tapin.dom.js'),
            true
        );

        wp_register_script(
            $ticketsHandle,
            $scriptBaseUrl . 'tapin.tickets.js',
            [$domHandle],
            $this->assetVersion($scriptBasePath . 'tapin.tickets.js'),
            true
        );

        wp_register_script(
            $planHandle,
            $scriptBaseUrl . 'tapin.plan.js',
            [$ticketsHandle],
            $this->assetVersion($scriptBasePath . 'tapin.plan.js'),
            true
        );

        wp_register_script(
            $formHandle,
            $scriptBaseUrl . 'tapin.form.js',
            [$planHandle],
            $this->assetVersion($scriptBasePath . 'tapin.form.js'),
            true
        );

        wp_register_script(
            $modalHandle,
            $scriptBaseUrl . 'tapin.modal.js',
            [$formHandle],
            $this->assetVersion($scriptBasePath . 'tapin.modal.js'),
            true
        );

        wp_register_script(
            $indexHandle,
            $scriptBaseUrl . 'tapin.index.js',
            [$modalHandle],
            $this->assetVersion($scriptBasePath . 'tapin.index.js'),
            true
        );

        wp_enqueue_script($indexHandle);
    }


    /**
     * Returns localized strings for the purchase modal UI.
     *
     * @return array<string,string>
     */
    private function getModalMessages(): array
    {
        if ($this->modalMessages !== null) {
            return $this->modalMessages;
        }

        $this->modalMessages = [
            'title'               => __('פרטי ההזמנה', 'tapin'),
            'ticketStepTitle'     => __('בחרו את הכרטיסים שלכם', 'tapin'),
            'ticketStepSubtitle'  => __('בחרו כמה כרטיסים ברצונכם לרכוש מכל סוג זמין.', 'tapin'),
            'ticketStepNext'      => __('המשך', 'tapin'),
            'ticketStepError'     => __('בחרו לפחות כרטיס אחד כדי להמשיך.', 'tapin'),
            'ticketStepSoldOut'   => __('אזל המלאי', 'tapin'),
            'ticketStepIncluded'  => __('כלול', 'tapin'),
            'ticketStepAvailability' => __('זמין: %s', 'tapin'),
            'ticketStepNoLimit'   => __('ללא הגבלה', 'tapin'),
            'ticketStepDecrease'  => __('הפחת כרטיס', 'tapin'),
            'ticketStepIncrease'  => __('הוסף כרטיס', 'tapin'),
            'ticketTotalLabel'    => __('סה״כ כרטיסים:', 'tapin'),
            'ticketHintLabel'     => __('סוג הכרטיס:', 'tapin'),
            'ticketSelectPlaceholder' => __('בחרו סוג כרטיס', 'tapin'),
            'ticketSelectError'   => __('בחרו סוג כרטיס עבור משתתף זה.', 'tapin'),
            'payerTitle'          => __('פרטי המזמין', 'tapin'),
            'participantTitle'    => __('משתתף %1$s', 'tapin'),
            'step'                => __('משתתף %1$s מתוך %2$s', 'tapin'),
            'next'                => __('הבא', 'tapin'),
            'finish'              => __('סיום והמשך לתשלום', 'tapin'),
            'cancel'              => __('ביטול', 'tapin'),
            'back'                => __('חזור', 'tapin'),
            'close'               => __('סגירת חלון', 'tapin'),
            'required'            => __('שדה חובה.', 'tapin'),
            'invalidEmail'        => __('הזינו כתובת דוא״ל תקינה.', 'tapin'),
            'invalidInstagram'    => __('הזינו שם משתמש אינסטגרם תקין.', 'tapin'),
            'invalidTikTok'       => __('הזינו שם משתמש טיקטוק תקין.', 'tapin'),
            'invalidFacebook'     => __('הזינו כתובת פייסבוק תקינה.', 'tapin'),
            'invalidPhone'        => __('הזינו מספר טלפון תקין (10 ספרות).', 'tapin'),
            'invalidId'           => __('הזינו מספר זהות תקין (9 ספרות).', 'tapin'),
        ];

        return $this->modalMessages;
    }

    public function renderHiddenField(): void
    {
        if ($this->isEligibleProduct()) {
            echo '<input type="hidden" name="tapin_attendees" id="tapinAttendeesField" value="">';
        }
    }

    public function renderModal(): void
    {
        if (!$this->isEligibleProduct()) {
            return;
        }

        $productId   = (int) get_the_ID();
        $ticketCache = $productId ? $this->ensureTicketTypeCache($productId) : ['list' => [], 'index' => []];
        $ticketTypes = $ticketCache['list'];
        $messages   = $this->getModalMessages();
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
                    <?php foreach ($this->getFieldDefinitions() as $fieldKey => $definition): ?>
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
                        ?>
                        <?php
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

    public function validateSubmission(bool $passed, int $productId, int $quantity, $variationId = 0, $variations = null): bool
    {
        $this->redirectNextAdd = false;
        if ($this->processingSplitAdd) {
            return $passed;
        }
        $this->pendingAttendees = [];
        $this->attendeeQueue = [];

        if ((is_admin() && !wp_doing_ajax()) || !$this->shouldHandleProduct($productId)) {
            return $passed;
        }

        if (!isset($_POST['tapin_attendees'])) {
            wc_add_notice('יש למלא את פרטי המשתתפים לפני הרכישה.', 'error');
            return false;
        }

        $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
        if (!is_array($decoded)) {
            wc_add_notice('נראה שיש בעיה בנתוני המשתתפים, אנא נסו שוב.', 'error');
            return false;
        }

        $quantity = max(1, (int) $quantity);
        if (count($decoded) !== $quantity) {
            wc_add_notice('יש להזין פרטים עבור כל משתתף שנבחר לרכישה.', 'error');
            return false;
        }

        $cache = $this->ensureTicketTypeCache($productId);
        $this->currentTicketTypeIndex = $cache['index'];

        $sanitized = [];
        $errors    = [];

        foreach ($decoded as $index => $attendee) {
            $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                wc_add_notice($message, 'error');
            }

            return false;
        }

        if ($sanitized === []) {
            wc_add_notice(__('לא נמצאו משתתפים תקינים לעיבוד. אנא נסו שוב.', 'tapin'), 'error');
            return false;
        }

        $typeCounts = [];
        foreach ($sanitized as $entry) {
            $typeId = isset($entry['ticket_type']) ? (string) $entry['ticket_type'] : '';
            if ($typeId === '' || !isset($this->currentTicketTypeIndex[$typeId])) {
                wc_add_notice(__('סוג הכרטיס שנבחר אינו זמין.', 'tapin'), 'error');
                return false;
            }
            $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
        }

        foreach ($typeCounts as $typeId => $count) {
            $context   = $this->currentTicketTypeIndex[$typeId];
            $capacity  = isset($context['capacity']) ? (int) $context['capacity'] : 0;
            $available = isset($context['available']) ? (int) $context['available'] : 0;
            if ($capacity > 0 && $available >= 0 && $count > $available) {
                $name = (string) ($context['name'] ?? $typeId);
                wc_add_notice(sprintf(__('אין מספיק זמינות עבור %s.', 'tapin'), esc_html($name)), 'error');
                return false;
            }
        }

        $payer   = $sanitized[0];
        $userId  = get_current_user_id();
        $created = false;

        if (!$userId) {
            $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
            if ($email === '') {
                wc_add_notice('כתובת האימייל אינה תקינה.', 'error');
                return false;
            }

            $existing = get_user_by('email', $email);
            if ($existing instanceof \WP_User) {
                $this->storePendingCheckout($sanitized, $productId, $quantity);
                $this->pendingAttendees = [];
                $this->redirectToLogin();
                return false;
            }

            $userId = $this->createTransparentUser($payer);
            if (!$userId) {
                return false;
            }

            $created = true;
        }

        $this->maybeUpdateUserProfile((int) $userId, $payer, $created);

        $this->pendingAttendees = $sanitized;
        $this->attendeeQueue = $sanitized;
        $_POST['quantity'] = 1;
        $_REQUEST['quantity'] = 1;
        return $passed;
    }

    public function attachCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $isGenerated = !empty($cartItemData['tapin_split_generated']);
        $attendees   = [];

        if ($isGenerated && isset($cartItemData['tapin_attendees']) && is_array($cartItemData['tapin_attendees'])) {
            $attendees = array_values(array_filter($cartItemData['tapin_attendees'], 'is_array'));
        } else {
            if ($this->attendeeQueue !== []) {
                $next = array_shift($this->attendeeQueue);
                if (is_array($next)) {
                    $attendees[] = $next;
                }
            } elseif ($this->pendingAttendees !== []) {
                $next = array_shift($this->pendingAttendees);
                if (is_array($next)) {
                    $attendees[] = $next;
                }
            }

            if ($attendees === [] && isset($_POST['tapin_attendees'])) {
                $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
                if (is_array($decoded)) {
                    $errors = [];
                    $rebuilt = [];
                    foreach ($decoded as $index => $attendee) {
                        $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0);
                        if ($result !== null) {
                            $rebuilt[] = $result;
                        }
                    }

                    if ($errors !== []) {
                        foreach ($errors as $message) {
                            wc_add_notice($message, 'error');
                        }

                        return $cartItemData;
                    }

                    $this->attendeeQueue = $rebuilt;
                    $this->pendingAttendees = $rebuilt;
                    $next = array_shift($this->attendeeQueue);
                    if (is_array($next)) {
                        $attendees[] = $next;
                    }
                }
            }
        }

        if ($attendees === []) {
            unset($cartItemData['tapin_split_generated']);
            return $cartItemData;
        }

        $attendee = $attendees[0];
        $price    = isset($attendee['ticket_price']) ? (float) $attendee['ticket_price'] : null;

        $cartItemData['tapin_attendees'] = [$attendee];
        $cartItemData['tapin_attendees_key'] = md5(wp_json_encode($attendee) . microtime(true));
        if ($price !== null) {
            $cartItemData['tapin_ticket_price'] = $price;
        }

        if (!$isGenerated) {
            $this->redirectNextAdd = true;

            $remaining = $this->attendeeQueue;
            $this->attendeeQueue = [];
            $this->pendingAttendees = [];

            if (!empty($remaining) && function_exists('WC') && WC()->cart instanceof \WC_Cart) {
                $this->processingSplitAdd = true;
                foreach ($remaining as $extraAttendee) {
                    if (!is_array($extraAttendee)) {
                        continue;
                    }
                    $extraPrice = isset($extraAttendee['ticket_price']) ? (float) $extraAttendee['ticket_price'] : null;
                    $extraData = [
                        'tapin_attendees'        => [$extraAttendee],
                        'tapin_attendees_key'    => md5(wp_json_encode($extraAttendee) . microtime(true)),
                        'tapin_split_generated'  => true,
                    ];
                    if ($extraPrice !== null) {
                        $extraData['tapin_ticket_price'] = $extraPrice;
                    }
                    WC()->cart->add_to_cart($productId, 1, $variationId, [], $extraData);
                }
                $this->processingSplitAdd = false;
            }
        }

        unset($cartItemData['tapin_split_generated']);

        return $cartItemData;
    }

    public function restoreCartItemFromSession(array $item, array $values): array
    {
        if (isset($values['tapin_attendees'])) {
            $item['tapin_attendees'] = $values['tapin_attendees'];
        }

        if (isset($values['tapin_ticket_price'])) {
            $item['tapin_ticket_price'] = (float) $values['tapin_ticket_price'];
        }

        return $item;
    }

    public function displayCartItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['tapin_attendees']) || !is_array($cartItem['tapin_attendees'])) {
            return $itemData;
        }

        $lines = [];
        foreach ($cartItem['tapin_attendees'] as $index => $attendee) {
            $name       = isset($attendee['full_name']) ? $attendee['full_name'] : '';
            $email      = isset($attendee['email']) ? $attendee['email'] : '';
            $typeLabel  = isset($attendee['ticket_type_label']) ? $attendee['ticket_type_label'] : '';
            $summary    = sprintf(__('משתתף %d: %s (%s)', 'tapin'), $index + 1, esc_html($name), esc_html($email));
            if ($typeLabel !== '') {
                $summary .= ' - ' . esc_html($typeLabel);
            }
            $lines[] = $summary;
        }

        if ($lines !== []) {
            $itemData[] = [
                'name'  => __('משתתפים', 'tapin'),
                'value' => implode('<br>', array_map('wp_kses_post', $lines)),
            ];
        }

        return $itemData;
    }

    /**
     * @param array<int,string> $hiddenKeys
     * @return array<int,string>
     */
    public function hideOrderItemMeta(array $hiddenKeys): array
    {
        $hiddenKeys[] = '_tapin_attendees_json';
        $hiddenKeys[] = '_tapin_attendees_key';
        $hiddenKeys[] = 'Tapin Attendees';
        return array_values(array_unique($hiddenKeys));
    }

    /**
     * @param array<int,\stdClass> $metaData
     * @param \WC_Order_Item $item
     * @return array<int,\stdClass>
     */
    public function filterFormattedMeta(array $metaData, $item): array
    {
        foreach ($metaData as $index => $meta) {
            if (!isset($meta->key)) {
                continue;
            }

            if (
                $meta->key === '_tapin_attendees_json'
                || $meta->key === '_tapin_attendees_key'
                || $meta->key === 'Tapin Attendees'
            ) {
                unset($metaData[$index]);
            }
        }

        return array_values($metaData);
    }

    public function storeOrderItemMeta($item, string $cartItemKey, array $values, $order): void
    {
        if (empty($values['tapin_attendees']) || !is_array($values['tapin_attendees'])) {
            return;
        }

        $normalizedAttendees = array_map(function (array $attendee): array {
            $clean = [];
            foreach (AttendeeFields::keys() as $key) {
                $clean[$key] = AttendeeFields::sanitizeValue($key, (string) ($attendee[$key] ?? ''));
            }
            return $clean;
        }, $values['tapin_attendees']);
        foreach ($values['tapin_attendees'] as $offset => $attendee) {
            if (!isset($normalizedAttendees[$offset])) {
                continue;
            }
            if (isset($attendee['ticket_price'])) {
                $normalizedAttendees[$offset]['ticket_price'] = (float) $attendee['ticket_price'];
            }
        }

        if (isset($values['tapin_ticket_price'])) {
            $item->update_meta_data('_tapin_ticket_price', (float) $values['tapin_ticket_price']);
        }

        $encryptedAttendees = AttendeeSecureStorage::encryptAttendees($normalizedAttendees);
        if ($encryptedAttendees !== '') {
            $item->update_meta_data('_tapin_attendees_json', $encryptedAttendees);
        }

        if (!empty($values['tapin_attendees_key'])) {
            $item->update_meta_data('_tapin_attendees_key', sanitize_text_field((string) $values['tapin_attendees_key']));
        }

        $item->delete_meta_data('Tapin Attendees');

        $maskedAttendees = AttendeeSecureStorage::maskAttendees($normalizedAttendees);

        foreach ($maskedAttendees as $index => $attendee) {
            $label = sprintf(__('משתתף %d', 'tapin'), $index + 1);
            $parts = [];
            foreach (AttendeeFields::summaryKeys() as $key) {
                $parts[] = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            }
            $item->update_meta_data($label, implode(' | ', $parts));
        }

        if ($order instanceof WC_Order) {
            $existing = AttendeeSecureStorage::upgradeAggregate($order->get_meta('_tapin_attendees', true));

            $bucketKey = $item->get_id() ?: ($values['tapin_attendees_key'] ?? $cartItemKey);
            $bucketKey = (string) $bucketKey;

            $existing['line_items'][$bucketKey] = [
                'item_id'    => (int) $item->get_id(),
                'source_key' => (string) ($values['tapin_attendees_key'] ?? $cartItemKey),
                'encrypted'  => $encryptedAttendees,
                'masked'     => $maskedAttendees,
                'count'      => count($normalizedAttendees),
                'updated'    => current_time('mysql'),
            ];

            $order->update_meta_data('_tapin_attendees', $existing);
        }
    }

    private function getPrefillData(): array
    {
        $fieldKeys = array_keys($this->getFieldDefinitions());
        $prefill   = [];
        foreach ($fieldKeys as $key) {
            $prefill[$key] = '';
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return (array) apply_filters('tapin_purchase_modal_prefill', $prefill, 0);
        }

        $user = get_userdata($userId);
        $userFirstName = '';
        $userLastName  = '';
        $displayName   = '';

        if ($user) {
            if (isset($prefill['email'])) {
                $prefill['email'] = sanitize_email($user->user_email);
            }

            $userFirstName = AttendeeFields::sanitizeValue('first_name', (string) $user->first_name);
            $userLastName  = AttendeeFields::sanitizeValue('last_name', (string) $user->last_name);
            $displayName   = trim((string) $user->display_name);
        }

        foreach (AttendeeFields::prefillMeta() as $fieldKey => $metaKeys) {
            if (!array_key_exists($fieldKey, $prefill)) {
                continue;
            }

            foreach ($metaKeys as $metaKey) {
                $raw = (string) get_user_meta($userId, $metaKey, true);
                if ($raw === '') {
                    continue;
                }

                $sanitized = AttendeeFields::sanitizeValue($fieldKey, $raw);
                if ($sanitized === '') {
                    $display = AttendeeFields::displayValue($fieldKey, $raw);
                    if ($display === '') {
                        continue;
                    }
                    $prefill[$fieldKey] = $this->formatPrefillValue($fieldKey, $display);
                    break;
                }

                $prefill[$fieldKey] = $this->formatPrefillValue($fieldKey, $sanitized);
                break;
            }
        }

        if (isset($prefill['first_name']) && $prefill['first_name'] === '' && $userFirstName !== '') {
            $prefill['first_name'] = $this->formatPrefillValue('first_name', $userFirstName);
        }

        if (isset($prefill['last_name']) && $prefill['last_name'] === '' && $userLastName !== '') {
            $prefill['last_name'] = $this->formatPrefillValue('last_name', $userLastName);
        }

        if ($displayName !== '') {
            $needsFirst = isset($prefill['first_name']) && $prefill['first_name'] === '';
            $needsLast  = isset($prefill['last_name']) && $prefill['last_name'] === '';

            if ($needsFirst || $needsLast) {
                $parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
                if (is_array($parts) && $parts !== []) {
                    $firstPart = array_shift($parts);
                    if ($needsFirst && $firstPart !== null) {
                        $prefill['first_name'] = $this->formatPrefillValue('first_name', (string) $firstPart);
                    }

                    if ($needsLast && $parts !== []) {
                        $prefill['last_name'] = $this->formatPrefillValue('last_name', implode(' ', $parts));
                    }
                }
            }
        }

        return (array) apply_filters('tapin_purchase_modal_prefill', $prefill, $userId);
    }

    private function formatPrefillValue(string $fieldKey, string $value): string
    {
        switch ($fieldKey) {
            case 'instagram':
            case 'tiktok':
                return AttendeeFields::displayValue($fieldKey, $value);

            case 'facebook':
                return AttendeeFields::displayValue('facebook', $value);

            case 'phone':
                return AttendeeFields::displayValue('phone', $value);

            case 'gender':
                $normalized = AttendeeFields::sanitizeValue('gender', $value);
                if ($normalized !== '') {
                    return $normalized;
                }
                $display = AttendeeFields::displayValue('gender', $value);
                $fallback = AttendeeFields::sanitizeValue('gender', $display);
                return $fallback !== '' ? $fallback : '';

            default:
                return $value;
        }
    }


    /**
     * @param mixed $product
     */
    public function maybeRedirectToCheckout($url, $product)
    {
        $shouldRedirect = $this->redirectNextAdd;

        if (!$shouldRedirect && isset($_POST['tapin_attendees'])) {
            if (!function_exists('wc_get_notices') || wc_get_notices('error') === []) {
                $shouldRedirect = true;
            }
        }

        if (!$shouldRedirect) {
            return $url;
        }

        $this->redirectNextAdd = false;

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }

        return $url;
    }

    public function applyAttendeePricing($cart): void
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $key => $item) {
            $price = null;
            if (isset($item['tapin_ticket_price'])) {
                $price = (float) $item['tapin_ticket_price'];
            } elseif (!empty($item['tapin_attendees'][0]['ticket_price'])) {
                $price = (float) $item['tapin_attendees'][0]['ticket_price'];
            }

            if ($price === null) {
                continue;
            }

            if (isset($cart->cart_contents[$key])) {
                $cart->cart_contents[$key]['tapin_ticket_price'] = $price;
            }

            if (isset($item['data']) && $item['data'] instanceof WC_Product) {
                $item['data']->set_price($price);
            }
        }
    }

    private function sanitizeAttendee(array $attendee, int $index, array &$errors, bool $isPayer): ?array
    {
        $definitions = $this->getFieldDefinitions();
        $clean       = [];
        $attendeeLabel = $this->attendeeLabel($index);

        foreach ($definitions as $key => $definition) {
            $raw    = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            $raw    = is_string($raw) ? $raw : '';
            $hasRaw = trim($raw) !== '';
            $value  = AttendeeFields::sanitizeValue($key, $raw);

            if ($value === '' && $hasRaw) {
                $display = AttendeeFields::displayValue($key, $raw);
                if ($display !== '') {
                    $fallback = AttendeeFields::sanitizeValue($key, $display);
                    if ($fallback !== '') {
                        $value = $fallback;
                    }
                }
            }

            $requirements = isset($definition['required_for']) && is_array($definition['required_for'])
                ? $definition['required_for']
                : ['payer' => true, 'attendee' => true];
            $isRequired   = $isPayer ? !empty($requirements['payer']) : !empty($requirements['attendee']);

            if ($value === '') {
                if ($hasRaw) {
                    $errors[] = $this->invalidFieldMessage($key, (string) ($definition['label'] ?? $key), $attendeeLabel);
                    return null;
                }

                if ($isRequired) {
                    $errors[] = $this->missingFieldMessage($key, (string) ($definition['label'] ?? $key), $attendeeLabel);
                    return null;
                }

                $clean[$key] = '';
                continue;
            }

            $clean[$key] = $value;
        }

        $ticketTypeId = isset($attendee['ticket_type']) ? sanitize_key((string) $attendee['ticket_type']) : '';
        $ticketTypeLabel = '';
        $ticketPrice = 0.0;
        if ($ticketTypeId !== '' && isset($this->currentTicketTypeIndex[$ticketTypeId])) {
            $context = $this->currentTicketTypeIndex[$ticketTypeId];
            $ticketTypeLabel = (string) ($context['name'] ?? '');
            if (isset($context['price'])) {
                $ticketPrice = (float) $context['price'];
            }
        }
        if ($ticketTypeLabel === '' && isset($attendee['ticket_type_label'])) {
            $ticketTypeLabel = sanitize_text_field((string) $attendee['ticket_type_label']);
        }
        $clean['ticket_type'] = $ticketTypeId;
        $clean['ticket_type_label'] = $ticketTypeLabel;
        $clean['ticket_price'] = $ticketPrice;

        $firstName = isset($clean['first_name']) ? (string) $clean['first_name'] : '';
        $lastName  = isset($clean['last_name']) ? (string) $clean['last_name'] : '';
        $fullName  = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $firstName !== '' ? $firstName : $lastName;
        }
        $clean['full_name'] = $fullName;

        return $clean;
    }

    private function missingFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf('יש להזין אימייל עבור %s', $attendeeLabel);
            case 'instagram':
                return sprintf('יש להזין אינסטגרם עבור %s', $attendeeLabel);
            case 'phone':
                return sprintf('יש להזין מספר טלפון עבור %s', $attendeeLabel);
            default:
                return sprintf('יש למלא את השדה %s עבור %s', $fieldLabel, $attendeeLabel);
        }
    }

    private function invalidFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf('כתובת האימייל אינה תקינה עבור %s', $attendeeLabel);
            case 'phone':
                return sprintf('מספר הטלפון חייב לכלול לפחות 10 ספרות עבור %s', $attendeeLabel);
            case 'id_number':
                return sprintf('תעודת זהות חייבת להכיל בדיוק 9 ספרות עבור %s', $attendeeLabel);
            case 'instagram':
                return sprintf('יש להזין אינסטגרם תקין (@username או קישור לפרופיל) עבור %s', $attendeeLabel);
            case 'tiktok':
                return sprintf('יש להזין טיקטוק תקין (@username או קישור לפרופיל) עבור %s', $attendeeLabel);
            case 'facebook':
                return sprintf('קישור הפייסבוק חייב לכלול facebook עבור %s', $attendeeLabel);
            default:
                return sprintf('הערך שסופק עבור %s אינו תקין עבור %s', $fieldLabel, $attendeeLabel);
        }
    }

    private function attendeeLabel(int $index): string
    {
        return $index === 0 ? 'הלקוח המשלם' : sprintf('משתתף %d', $index + 1);
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function getFieldDefinitions(): array
    {
        $definitions = AttendeeFields::definitions();
        $labels = apply_filters('tapin_purchase_modal_fields', AttendeeFields::labels());

        if (is_array($labels)) {
            foreach ($definitions as $key => &$definition) {
                if (isset($labels[$key])) {
                    $definition['label'] = (string) $labels[$key];
                }
            }
            unset($definition);
        }

        foreach ($definitions as $key => &$definition) {
            $definition['required_for'] = AttendeeFields::requiredFor($key);
        }
        unset($definition);

        return $definitions;
    }

    public function maybeResumePendingCheckout(): void
    {
        if (!is_user_logged_in() || !function_exists('WC')) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $session = WC()->session;
        if (!$session) {
            return;
        }

        $pending = $session->get(self::SESSION_KEY_PENDING);
        if (!is_array($pending) || empty($pending['attendees']) || empty($pending['product_id'])) {
            return;
        }

        $session->set(self::SESSION_KEY_PENDING, null);

        $productId = (int) ($pending['product_id'] ?? 0);
        $quantity  = max(1, (int) ($pending['quantity'] ?? 1));
        $rawAttendees = is_array($pending['attendees']) ? $pending['attendees'] : [];

        $errors    = [];
        $sanitized = [];
        foreach ($rawAttendees as $index => $entry) {
            $result = $this->sanitizeAttendee(is_array($entry) ? $entry : [], $index, $errors, $index === 0);
            if ($result !== null) {
                $sanitized[] = $result;
            }
        }

        if ($errors !== [] || $sanitized === []) {
            return;
        }

        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($productId);
        if (
            !$product instanceof WC_Product ||
            !$product->is_purchasable() ||
            !ProductAvailability::isCurrentlyPurchasable($productId)
        ) {
            return;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $payload = wp_json_encode($sanitized);
        if (is_string($payload)) {
            $_POST['tapin_attendees'] = $payload;
            $_POST['quantity']        = (string) $quantity;
        }

        $cartItemKey = $cart->add_to_cart(
            $productId,
            $quantity,
            0,
            [],
            [
                'tapin_attendees'     => $sanitized,
                'tapin_attendees_key' => md5(wp_json_encode($sanitized) . microtime(true)),
            ]
        );

        unset($_POST['tapin_attendees'], $_POST['quantity']);

        if ($cartItemKey) {
            $payer = $sanitized[0] ?? [];
            $this->maybeUpdateUserProfile(get_current_user_id(), $payer, false);
        }
    }

    private function storePendingCheckout(array $attendees, int $productId, int $quantity): void
    {
        if (!function_exists('WC')) {
            return;
        }

        $session = WC()->session;
        if (!$session) {
            return;
        }

        $payload = [
            'product_id' => (int) $productId,
            'quantity'   => max(1, (int) $quantity),
            'attendees'  => $attendees,
            'timestamp'  => time(),
        ];

        $session->set(self::SESSION_KEY_PENDING, $payload);
    }

    private function redirectToLogin(): void
    {
        $url = $this->loginRedirectUrl();
        if ($url === '') {
            $url = home_url('/');
        }

        wp_safe_redirect($url);
        exit;
    }

    private function loginRedirectUrl(): string
    {
        $checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');

        if (function_exists('wc_get_page_permalink')) {
            $accountUrl = wc_get_page_permalink('myaccount');
            if ($accountUrl) {
                return add_query_arg('redirect_to', rawurlencode($checkout), $accountUrl);
            }
        }

        return wp_login_url($checkout);
    }

    private function createTransparentUser(array $payer): ?int
    {
        $email = isset($payer['email']) ? sanitize_email($payer['email']) : '';
        if ($email === '') {
            wc_add_notice('כתובת האימייל אינה תקינה.', 'error');
            return null;
        }

        $firstName = sanitize_text_field($payer['first_name'] ?? '');
        $lastName  = sanitize_text_field($payer['last_name'] ?? '');
        $username  = $this->generateUsername($firstName, $lastName, $email);
        $password  = wp_generate_password(32, true);

        $userId = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'customer',
        ]);

        if (is_wp_error($userId)) {
            wc_add_notice('לא ניתן היה ליצור משתמש חדש, אנא נסו שוב או פנו לתמיכה.', 'error');
            return null;
        }

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);
        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($userId);
        }

        $user = get_userdata($userId);
        if ($user && $user->user_login) {
            do_action('wp_login', $user->user_login, $user);
        }

        return (int) $userId;
    }

    private function generateUsername(string $firstName, string $lastName, string $email): string
    {
        $candidate = sanitize_user($firstName . $lastName, true);
        if ($candidate === '') {
            $parts = explode('@', $email);
            $candidate = sanitize_user($parts[0] ?? '', true);
        }
        if ($candidate === '') {
            $candidate = 'tapin_user';
        }

        $username = $candidate;
        $suffix   = 1;
        while (username_exists($username)) {
            $username = $candidate . $suffix;
            $suffix++;
        }

        return $username;
    }

    private function maybeUpdateUserProfile(int $userId, array $payer, bool $force = false): void
    {
        if ($userId <= 0) {
            return;
        }

        $fields = [
            'first_name' => sanitize_text_field($payer['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($payer['last_name'] ?? ''),
            'phone'      => AttendeeFields::sanitizeValue('phone', (string) ($payer['phone'] ?? '')),
            'instagram'  => AttendeeFields::sanitizeValue('instagram', (string) ($payer['instagram'] ?? '')),
            'tiktok'     => AttendeeFields::sanitizeValue('tiktok', (string) ($payer['tiktok'] ?? '')),
            'facebook'   => AttendeeFields::sanitizeValue('facebook', (string) ($payer['facebook'] ?? '')),
            'birth_date' => AttendeeFields::sanitizeValue('birth_date', (string) ($payer['birth_date'] ?? '')),
            'gender'     => AttendeeFields::sanitizeValue('gender', (string) ($payer['gender'] ?? '')),
            'id_number'  => AttendeeFields::sanitizeValue('id_number', (string) ($payer['id_number'] ?? '')),
        ];

        $this->maybeUpdateMeta($userId, 'first_name', $fields['first_name'], $force);
        $this->maybeUpdateMeta($userId, 'last_name', $fields['last_name'], $force);
        $this->maybeUpdateMeta($userId, 'billing_first_name', $fields['first_name'], $force);
        $this->maybeUpdateMeta($userId, 'billing_last_name', $fields['last_name'], $force);

        $phone = $fields['phone'];
        if ($phone !== '') {
            $this->maybeUpdateMeta($userId, 'billing_phone', $phone, $force);
            $this->maybeUpdateMeta($userId, 'phone_whatsapp', preg_replace('/\D+/', '', $phone), $force);
        }

        $instagramHandle = $fields['instagram'];
        if ($instagramHandle !== '') {
            $rawInstagram = (string) get_user_meta($userId, 'instagram', true);
            $currentHandle = AttendeeFields::sanitizeValue('instagram', $rawInstagram);
            if ($force || $currentHandle === '' || $currentHandle !== $instagramHandle || trim($rawInstagram) !== $instagramHandle) {
                update_user_meta($userId, 'instagram', $instagramHandle);
                update_user_meta($userId, 'instagram_url', 'https://instagram.com/' . $instagramHandle);
            }
        }

        $tiktokHandle = $fields['tiktok'];
        if ($tiktokHandle !== '') {
            $rawTiktok   = (string) get_user_meta($userId, 'tiktok', true);
            $currentTikTok = AttendeeFields::sanitizeValue('tiktok', $rawTiktok);
            if ($force || $currentTikTok === '' || $currentTikTok !== $tiktokHandle || trim($rawTiktok) !== $tiktokHandle) {
                update_user_meta($userId, 'tiktok', $tiktokHandle);
                update_user_meta($userId, 'tiktok_url', 'https://www.tiktok.com/@' . $tiktokHandle);
            }
        }

        if ($fields['facebook'] !== '') {
            $rawFacebook    = (string) get_user_meta($userId, 'facebook', true);
            $currentFacebook = AttendeeFields::sanitizeValue('facebook', $rawFacebook);
            if ($force || $currentFacebook === '' || $currentFacebook !== $fields['facebook'] || trim($rawFacebook) !== $fields['facebook']) {
                update_user_meta($userId, 'facebook', $fields['facebook']);
                update_user_meta($userId, 'facebook_url', $fields['facebook']);
            }
        }

        if ($fields['birth_date'] !== '') {
            $this->maybeUpdateMeta($userId, 'birth_date', $fields['birth_date'], $force);
            $this->maybeUpdateMeta($userId, 'um_birth_date', $fields['birth_date'], $force);
        }

        if ($fields['gender'] !== '') {
            $this->maybeUpdateMeta($userId, 'gender', $fields['gender'], $force);
            $this->maybeUpdateMeta($userId, 'um_gender', $fields['gender'], $force);
        }

        if ($fields['id_number'] !== '') {
            $this->maybeUpdateMeta($userId, 'id_number', $fields['id_number'], $force);
        }

        $user = get_userdata($userId);
        if ($user) {
            $displayCandidates = trim(($fields['first_name'] ? $fields['first_name'] . ' ' : '') . $fields['last_name']);
            if ($displayCandidates !== '') {
                $currentDisplay = trim((string) $user->display_name);
                $shouldUpdateDisplay = $force
                    || $currentDisplay === ''
                    || $currentDisplay === $user->user_login
                    || $currentDisplay === $user->user_email;

                if ($shouldUpdateDisplay) {
                    wp_update_user([
                        'ID'           => $userId,
                        'display_name' => $displayCandidates,
                        'nickname'     => $displayCandidates,
                    ]);
                }
            }
        }
    }

    /**
     * @return array{list: array<int,array<string,mixed>>, index: array<string,array<string,mixed>>}
     */
    private function ensureTicketTypeCache(int $productId): array
    {
        if (!isset($this->ticketTypeCache[$productId])) {
            $rawTypes = TicketTypesRepository::get($productId);
            $activeWindow = SaleWindowsRepository::findActive($productId, $rawTypes);
            $list = [];
            $index = [];

            foreach ($rawTypes as $type) {
                if (!is_array($type)) {
                    continue;
                }

                $id = isset($type['id']) ? (string) $type['id'] : '';
                if ($id === '') {
                    continue;
                }

                $basePrice = isset($type['base_price']) ? (float) $type['base_price'] : 0.0;
                $price = $basePrice;
                if (is_array($activeWindow) && isset($activeWindow['prices'][$id]) && (float) $activeWindow['prices'][$id] > 0) {
                    $price = (float) $activeWindow['prices'][$id];
                }

                $available = isset($type['available']) ? (int) $type['available'] : 0;
                $capacity  = isset($type['capacity']) ? (int) $type['capacity'] : 0;

                if ($capacity > 0 && $available > $capacity) {
                    $available = $capacity;
                }

                $available = max(0, $available);
                $capacity  = max(0, $capacity);
                $isSoldOut = $capacity > 0 && $available <= 0;

                $entry = [
                    'id'                 => $id,
                    'name'               => (string) ($type['name'] ?? $id),
                    'description'        => (string) ($type['description'] ?? ''),
                    'price'              => $price,
                    'base_price'         => $basePrice,
                    'available'          => $available,
                    'capacity'           => $capacity,
                    'price_html'         => $this->formatTicketPrice($price),
                    'availability_label' => $this->formatAvailability($capacity, $available),
                    'sold_out'           => $isSoldOut,
                ];

                $list[]  = $entry;
                $index[$id] = $entry;
            }

            $this->ticketTypeCache[$productId] = [
                'list'  => $list,
                'index' => $index,
            ];
        }

        return $this->ticketTypeCache[$productId];
    }

    private function formatTicketPrice(float $price): string
    {
        if ($price <= 0.0) {
            $messages = $this->getModalMessages();
            return esc_html($messages['ticketStepIncluded'] ?? __('כלול', 'tapin'));
        }
        if (function_exists('wc_price')) {
            return wc_price($price);
        }

        return number_format_i18n($price, 2);
    }

    private function formatAvailability(int $capacity, int $available): string
    {
        $messages = $this->getModalMessages();
        if ($capacity <= 0) {
            return esc_html($messages['ticketStepNoLimit'] ?? __('ללא הגבלה', 'tapin'));
        }

        $template = (string) ($messages['ticketStepAvailability'] ?? __('זמין: %s', 'tapin'));
        return sprintf($template, max(0, $available));
    }

    private function maybeUpdateMeta(int $userId, string $metaKey, string $value, bool $force): void
    {
        if ($value === '') {
            return;
        }

        $current = get_user_meta($userId, $metaKey, true);
        if (!$force && trim((string) $current) !== '') {
            return;
        }

        update_user_meta($userId, $metaKey, $value);
    }

    private function isEligibleProduct(): bool
    {
        if (!function_exists('is_product') || !function_exists('wc_get_product') || !is_product()) {
            return false;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product instanceof WC_Product) {
            return false;
        }

        if (!$product->is_type('simple')) {
            return false;
        }

        if (!$product->is_purchasable()) {
            return false;
        }

        return ProductAvailability::isCurrentlyPurchasable((int) $product->get_id());
    }

    private function shouldHandleProduct(int $productId): bool
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            return false;
        }

        if (!$product->is_type('simple')) {
            return false;
        }

        if (!$product->is_purchasable()) {
            return false;
        }

        return ProductAvailability::isCurrentlyPurchasable($productId);
    }

    private function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }
}
