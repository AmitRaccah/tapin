<?php

namespace Tapin\Events\Features\ProductPage;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\AttendeeSecureStorage;
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
    private bool $redirectNextAdd   = false;

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

        return 'לקנייה';
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

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $assetsDirUrl . 'purchase-modal.js',
            [],
            $this->assetVersion($assetsDirPath . 'purchase-modal.js'),
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'TapinPurchaseModalData', [
            'prefill'  => $this->getPrefillData(),
            'messages' => [
                'title'            => 'פרטי משתתפים',
                'payerTitle'       => 'פרטי הלקוח המשלם',
                'participantTitle' => 'פרטי משתתף %1$s',
                'step'             => 'משתתף %1$s מתוך %2$s',
                'next'             => 'הבא',
                'finish'           => 'סיום והמשך לתשלום',
                'cancel'           => 'ביטול',
                'quantityTitle'    => 'בחירת כמות כרטיסים',
                'quantitySubtitle' => 'בחרו כמה כרטיסים תרצו לרכוש',
                'quantityNext'     => 'המשך',
                'quantitySingular' => 'כרטיס',
                'quantityPlural'   => 'כרטיסים',
                'quantityIncrease' => 'עוד כרטיס',
                'quantityDecrease' => 'פחות כרטיס',
                'required'         => 'יש למלא את כל השדות',
                'invalidEmail'     => 'כתובת האימייל אינה תקינה',
                'invalidInstagram' => 'יש להזין אינסטגרם תקין (@username או קישור לפרופיל)',
                'invalidTikTok'    => 'יש להזין טיקטוק תקין (@username או קישור לפרופיל)',
                'invalidFacebook'  => 'קישור הפייסבוק חייב לכלול facebook',
                'invalidPhone'     => 'מספר הטלפון חייב לכלול לפחות 10 ספרות',
                'invalidId'        => 'תעודת זהות חייבת להכיל 9 ספרות',
            ],
            'fields'   => $this->getFieldDefinitions(),
        ]);
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
        } ?>
        <div id="tapinPurchaseModal" class="tapin-purchase-modal" hidden>
            <div class="tapin-purchase-modal__backdrop" data-modal-dismiss></div>
            <div class="tapin-purchase-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tapinPurchaseModalTitle">
                <button type="button" class="tapin-purchase-modal__close" data-modal-dismiss aria-label="סגור חלון">&times;</button>
                <h2 id="tapinPurchaseModalTitle" class="tapin-purchase-modal__title"></h2>
                <p class="tapin-purchase-modal__subtitle" data-step-text></p>
                <div class="tapin-quantity-step" data-quantity-step hidden>
                    <div class="tapin-quantity-step__controls" dir="ltr">
                        <button
                            type="button"
                            class="tapin-quantity-step__btn tapin-quantity-step__btn--decrease"
                            data-quantity-action="decrease"
                            aria-label="פחות כרטיס"
                        >-</button>
                        <span
                            class="tapin-quantity-step__value"
                            data-quantity-value
                            aria-live="polite"
                            aria-atomic="true"
                        >1</span>
                        <button
                            type="button"
                            class="tapin-quantity-step__btn tapin-quantity-step__btn--increase"
                            data-quantity-action="increase"
                            aria-label="עוד כרטיס"
                        >+</button>
                    </div>
                </div>
                <div class="tapin-purchase-modal__form" data-form-container hidden>
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
                    <button type="button" class="tapin-btn tapin-btn--primary" data-modal-action="next">הבא</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function validateSubmission(bool $passed, int $productId, int $quantity, $variationId = 0, $variations = null): bool
    {
        $this->redirectNextAdd = false;

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
            wc_add_notice('יש למלא את פרטי המשתתפים לפני הרכישה.', 'error');
            return false;
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
        return $passed;
    }

    public function attachCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $attendees = $this->pendingAttendees;
        $this->pendingAttendees = [];

        if ($attendees === [] && isset($_POST['tapin_attendees'])) {
            $decoded = json_decode(wp_unslash((string) $_POST['tapin_attendees']), true);
            if (is_array($decoded)) {
                $errors = [];
                foreach ($decoded as $index => $attendee) {
                    $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors, $index === 0);
                    if ($result !== null) {
                        $attendees[] = $result;
                    }
                }

                if ($errors !== []) {
                    foreach ($errors as $message) {
                        wc_add_notice($message, 'error');
                    }

                    return $cartItemData;
                }
            }
        }

        if ($attendees !== []) {
            $cartItemData['tapin_attendees'] = $attendees;
            $cartItemData['tapin_attendees_key'] = md5(wp_json_encode($attendees) . microtime(true));
            $this->redirectNextAdd = true;
        }

        return $cartItemData;
    }

    public function restoreCartItemFromSession(array $item, array $values): array
    {
        if (isset($values['tapin_attendees'])) {
            $item['tapin_attendees'] = $values['tapin_attendees'];
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
            $name  = isset($attendee['full_name']) ? $attendee['full_name'] : '';
            $email = isset($attendee['email']) ? $attendee['email'] : '';
            $lines[] = sprintf('משתתף %d: %s (%s)', $index + 1, esc_html($name), esc_html($email));
        }

        if ($lines !== []) {
            $itemData[] = [
                'name'  => 'פרטי משתתפים',
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
            $label = sprintf('משתתף %d', $index + 1);
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
        if (!$this->redirectNextAdd) {
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
        if (!$product instanceof WC_Product || !$product->is_purchasable()) {
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

        return $product->is_purchasable() && $product->is_type('simple');
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

        return $product->is_purchasable() && $product->is_type('simple');
    }

    private function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }
}
