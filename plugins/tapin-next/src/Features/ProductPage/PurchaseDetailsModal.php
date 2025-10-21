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
    private const SCRIPT_HANDLE = 'tapin-purchase-modal';
    private const STYLE_HANDLE  = 'tapin-purchase-modal';

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
                'title'        => 'פרטי משתתפים',
                'step'         => 'משתתף %1$s מתוך %2$s',
                'next'         => 'הבא',
                'finish'       => 'סיום והמשך לתשלום',
                'cancel'       => 'ביטול',
                'required'         => 'יש למלא את כל השדות',
                'invalidEmail'     => 'כתובת האימייל אינה תקינה',
                'invalidInstagram' => 'אינסטגרם חייב להתחיל ב-@ או להכיל instagram.com',
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
                <div class="tapin-purchase-modal__form" data-form-container>
                    <?php foreach ($this->getFieldDefinitions() as $fieldKey => $definition): ?>
                        <?php
                        $label = (string) ($definition['label'] ?? $fieldKey);
                        $type  = (string) ($definition['type'] ?? 'text');
                        $inputType = in_array($type, ['email', 'date'], true) ? $type : 'text';
                        $choices = isset($definition['choices']) && is_array($definition['choices'])
                            ? $definition['choices']
                            : [];
                        ?>
                        <div class="tapin-field<?php echo $type === 'choice' ? ' tapin-field--choice' : ''; ?>">
                            <label for="tapin-field-<?php echo esc_attr($fieldKey); ?>">
                                <?php echo esc_html($label); ?> <span class="tapin-required">*</span>
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
                                    data-field-type="choice"
                                    required
                                >
                            <?php else: ?>
                                <input
                                    type="<?php echo esc_attr($inputType); ?>"
                                    id="tapin-field-<?php echo esc_attr($fieldKey); ?>"
                                    data-field="<?php echo esc_attr($fieldKey); ?>"
                                    data-field-type="<?php echo esc_attr($type); ?>"
                                    required
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
            $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors);
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
                    $result = $this->sanitizeAttendee(is_array($attendee) ? $attendee : [], $index, $errors);
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
        $prefill = array_fill_keys(AttendeeFields::keys(), '');

        $userId = get_current_user_id();
        if (!$userId) {
            return (array) apply_filters('tapin_purchase_modal_prefill', $prefill, 0);
        }

        $user = get_userdata($userId);
        if ($user) {
            $prefill['email'] = sanitize_email($user->user_email);
            $prefill['full_name'] = sanitize_text_field($user->display_name);
        }

        foreach (AttendeeFields::prefillMeta() as $fieldKey => $metaKeys) {
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

        return (array) apply_filters('tapin_purchase_modal_prefill', $prefill, $userId);
    }

    private function formatPrefillValue(string $fieldKey, string $value): string
    {
        switch ($fieldKey) {
            case 'instagram':
                if (preg_match('#instagram\.com/([^/?#]+)#i', $value, $matches)) {
                    return '@' . $matches[1];
                }
                return '@' . ltrim($value, '@/');

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

    private function sanitizeAttendee(array $attendee, int $index, array &$errors): ?array
    {
        $definitions = $this->getFieldDefinitions();
        $clean       = [];

        foreach ($definitions as $key => $definition) {
            $raw   = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            $value = AttendeeFields::sanitizeValue($key, $raw);

            if ($value === '' && $raw !== '') {
                $display = AttendeeFields::displayValue($key, $raw);
                if ($display !== '') {
                    $fallback = AttendeeFields::sanitizeValue($key, $display);
                    $value = $fallback !== '' ? $fallback : $display;
                }
            }

            if ($value === '') {
                switch ($key) {
                    case 'email':
                        $errors[] = sprintf('האימייל חייב להכיל @ עבור משתתף %d', $index + 1);
                        break;
                    case 'id_number':
                        $errors[] = sprintf('תעודת זהות חייבת להכיל בדיוק 9 ספרות עבור משתתף %d', $index + 1);
                        break;
                    case 'instagram':
                        $errors[] = sprintf('אינסטגרם חייב להיות תאג (@username) או קישור תקין לפרופיל עבור משתתף %d', $index + 1);
                        break;
                    case 'facebook':
                        $errors[] = sprintf('קישור הפייסבוק חייב לכלול facebook עבור משתתף %d', $index + 1);
                        break;
                    case 'phone':
                        $errors[] = sprintf('מספר הטלפון חייב להכיל לפחות 10 ספרות עבור משתתף %d', $index + 1);
                        break;
                    default:
                        $label = (string) ($definition['label'] ?? $key);
                        $errors[] = sprintf('יש למלא את השדה %s עבור משתתף %d', $label, $index + 1);
                        break;
                }
                return null;
            }

            $clean[$key] = $value;
        }

        return $clean;
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

        return $definitions;
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
