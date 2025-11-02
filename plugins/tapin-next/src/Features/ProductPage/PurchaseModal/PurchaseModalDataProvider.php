<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal;

use Tapin\Events\Domain\SaleWindowsRepository;
use Tapin\Events\Domain\TicketTypesRepository;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\ProductAvailability;
use WC_Product;

final class PurchaseModalDataProvider
{
    /** @var array<int,array{list:array<int,array<string,mixed>>,index:array<string,array<string,mixed>>> */
    private array $ticketTypeCache = [];
    /** @var array<string,string>|null */
    private ?array $modalMessages = null;
    /** @var array<string,array<string,mixed>>|null */
    private ?array $fieldDefinitions = null;

    /**
     * Returns localized strings for the purchase modal UI.
     *
     * @return array<string,string>
     */
    public function getModalMessages(): array
    {
        if ($this->modalMessages !== null) {
            return $this->modalMessages;
        }

        $this->modalMessages = [
            'title'                 => __('פרטי ההזמנה', 'tapin'),
            'ticketStepTitle'       => __('בחרו את הכרטיסים שלכם', 'tapin'),
            'ticketStepSubtitle'    => __('בחרו כמה כרטיסים ברצונכם לרכוש מכל סוג זמין.', 'tapin'),
            'ticketStepNext'        => __('המשך', 'tapin'),
            'ticketStepError'       => __('בחרו לפחות כרטיס אחד כדי להמשיך.', 'tapin'),
            'ticketStepSoldOut'     => __('אזל המלאי', 'tapin'),
            'ticketStepIncluded'    => __('כלול', 'tapin'),
            'ticketStepAvailability'=> __('זמין: %s', 'tapin'),
            'ticketStepNoLimit'     => __('ללא הגבלה', 'tapin'),
            'ticketStepDecrease'    => __('הפחת כרטיס', 'tapin'),
            'ticketStepIncrease'    => __('הוסף כרטיס', 'tapin'),
            'ticketTotalLabel'      => __('סה״כ כרטיסים:', 'tapin'),
            'ticketHintLabel'       => __('סוג הכרטיס:', 'tapin'),
            'ticketSelectPlaceholder' => __('בחרו סוג כרטיס', 'tapin'),
            'ticketSelectError'     => __('בחרו סוג כרטיס עבור משתתף זה.', 'tapin'),
            'payerTitle'            => __('פרטי המזמין', 'tapin'),
            'participantTitle'      => __('משתתף %1$s', 'tapin'),
            'step'                  => __('משתתף %1$s מתוך %2$s', 'tapin'),
            'next'                  => __('הבא', 'tapin'),
            'finish'                => __('סיום והמשך לתשלום', 'tapin'),
            'cancel'                => __('ביטול', 'tapin'),
            'close'                 => __('סגירת חלון', 'tapin'),
            'required'              => __('שדה חובה.', 'tapin'),
            'invalidEmail'          => __('הזינו כתובת דוא״ל תקינה.', 'tapin'),
            'invalidInstagram'      => __('הזינו שם משתמש אינסטגרם תקין.', 'tapin'),
            'invalidTikTok'         => __('הזינו שם משתמש טיקטוק תקין.', 'tapin'),
            'invalidFacebook'       => __('הזינו כתובת פייסבוק תקינה.', 'tapin'),
            'invalidPhone'          => __('הזינו מספר טלפון תקין (10 ספרות).', 'tapin'),
            'invalidId'             => __('הזינו מספר זהות תקין (9 ספרות).', 'tapin'),
            'fieldsStepTitle'       => __('השלמת פרטים', 'tapin'),
            'fieldsStepSubtitle'    => __('מלאו את פרטי המשתתפים לכל כרטיס.', 'tapin'),
            'fieldsStepError'       => __('בחרו לפחות כרטיס אחד כדי להמשיך.', 'tapin'),
            'fieldsStepBack'        => __('חזרה', 'tapin'),
            'fieldsStepNext'        => __('הבא', 'tapin'),
            'summaryStepTitle'      => __('סקירת ההזמנה', 'tapin'),
            'summaryStepSubtitle'   => __('בדקו שהכל נראה תקין לפני ההמשך.', 'tapin'),
            'summaryStepBack'       => __('חזרה', 'tapin'),
            'summaryStepConfirm'    => __('סיום והמשך לתשלום', 'tapin'),
            'summaryTicketLabel'    => __('פרטי הכרטיסים', 'tapin'),
            'summaryCustomerLabel'  => __('פרטי המשלם', 'tapin'),
            'summaryAttendeesLabel' => __('משתתפים', 'tapin'),
        ];

        return $this->modalMessages;
    }
    public function getFieldDefinitions(): array
    {
        if ($this->fieldDefinitions !== null) {
            return $this->fieldDefinitions;
        }

        $definitions = AttendeeFields::definitions();
        $labels      = apply_filters('tapin_purchase_modal_fields', AttendeeFields::labels());

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

        $this->fieldDefinitions = $definitions;

        return $this->fieldDefinitions;
    }

    /**
     * @return array<string,string>
     */
    public function getPrefillData(): array
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

        $user          = get_userdata($userId);
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

    /**
     * @return array{list:array<int,array<string,mixed>>,index:array<string,array<string,mixed>>}
     */
    public function ensureTicketTypeCache(int $productId): array
    {
        if (!isset($this->ticketTypeCache[$productId])) {
            $ticketTypes    = TicketTypesRepository::get($productId);
            $saleWindows    = SaleWindowsRepository::get($productId, $ticketTypes);
            $activeWindow   = null;
            $now            = time();

            foreach ($saleWindows as $window) {
                $start = isset($window['start']) ? (int) $window['start'] : 0;
                $end   = isset($window['end']) ? (int) $window['end'] : 0;
                $isActive = $start <= $now && ($end === 0 || $now < $end);
                if ($isActive) {
                    $activeWindow = $window;
                    break;
                }
            }

            $list  = [];
            $index = [];

            foreach ($ticketTypes as $id => $type) {
                $basePrice = isset($type['price']) ? (float) $type['price'] : 0.0;
                $price     = $basePrice;

                if ($activeWindow && isset($activeWindow['prices'][$id])) {
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

                $list[]    = $entry;
                $index[$id] = $entry;
            }

            $this->ticketTypeCache[$productId] = [
                'list'  => $list,
                'index' => $index,
            ];
        }

        return $this->ticketTypeCache[$productId];
    }

    public function formatTicketPrice(float $price): string
    {
        if ($price <= 0.0) {
            $messages = $this->getModalMessages();
            return esc_html($messages['ticketStepIncluded'] ?? __('?>?????', 'tapin'));
        }
        if (function_exists('wc_price')) {
            return wc_price($price);
        }

        return number_format_i18n($price, 2);
    }

    public function formatAvailability(int $capacity, int $available): string
    {
        $messages = $this->getModalMessages();
        if ($capacity <= 0) {
            return esc_html($messages['ticketStepNoLimit'] ?? __('?????? ?"?'?`???"', 'tapin'));
        }

        $template = (string) ($messages['ticketStepAvailability'] ?? __('?-???T??: %s', 'tapin'));
        return sprintf($template, max(0, $available));
    }

    public function assetVersion(string $path): string
    {
        $mtime = file_exists($path) ? filemtime($path) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }

    public function isEligibleProduct(): bool
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

    public function shouldHandleProduct(int $productId): bool
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
                $display  = AttendeeFields::displayValue('gender', $value);
                $fallback = AttendeeFields::sanitizeValue('gender', $display);
                return $fallback !== '' ? $fallback : '';

            default:
                return $value;
        }
    }
}

