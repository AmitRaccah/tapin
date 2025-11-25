<?php

namespace Tapin\Events\Features\ProductPage\PurchaseModal\Validation;

use Tapin\Events\Features\ProductPage\PurchaseModal\Fields\FieldDefinitionsProvider;
use Tapin\Events\Support\AttendeeFields;
use Tapin\Events\Support\TicketTypeTracer;

final class AttendeeSanitizer
{
    private FieldDefinitionsProvider $definitions;

    public function __construct(FieldDefinitionsProvider $definitions)
    {
        $this->definitions = $definitions;
    }

    /**
     * @param array<string,mixed> $attendee
     * @param array<string,array<string,mixed>> $ticketTypeIndex
     * @param array<int,string> $errors
     * @return array<string,mixed>|null
     */
    public function sanitizeAttendee(array $attendee, int $index, array &$errors, bool $isPayer, array $ticketTypeIndex): ?array
    {
        $definitions = $this->definitions->getDefinitions();
        $clean = [];
        $attendeeLabel = $this->attendeeLabel($index);

        foreach ($definitions as $key => $definition) {
            $raw = isset($attendee[$key]) ? (string) $attendee[$key] : '';
            $raw = is_string($raw) ? $raw : '';
            $hasRaw = trim($raw) !== '';
            $value = AttendeeFields::sanitizeValue($key, $raw);

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
            $isRequired = $isPayer ? !empty($requirements['payer']) : !empty($requirements['attendee']);

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

        if ($ticketTypeId === '' && !empty($attendee['ticket_type_select'])) {
            $ticketTypeId = sanitize_key((string) $attendee['ticket_type_select']);
        }
        if (empty($attendee['ticket_type_label']) && !empty($attendee['ticket_type_select_label'])) {
            $attendee['ticket_type_label'] = (string) $attendee['ticket_type_select_label'];
        }

        if ($ticketTypeId !== '' && isset($ticketTypeIndex[$ticketTypeId])) {
            $context = $ticketTypeIndex[$ticketTypeId];
            $ticketTypeLabel = (string) ($context['name'] ?? '');
            if (isset($context['price'])) {
                $ticketPrice = (float) $context['price'];
            }
        }

        if ($ticketTypeId === '') {
            $rawLabel = isset($attendee['ticket_type_label']) ? (string) $attendee['ticket_type_label'] : '';
            $needle = AttendeeFields::normalizeLabel($rawLabel);
            if ($needle !== '') {
                foreach ($ticketTypeIndex as $idCandidate => $ctx) {
                    $nameCandidate = isset($ctx['name']) ? (string) $ctx['name'] : '';
                    if ($nameCandidate !== '' && AttendeeFields::labelsEqual($nameCandidate, $rawLabel)) {
                        $ticketTypeId = (string) $idCandidate;
                        $ticketTypeLabel = $nameCandidate;
                        if (isset($ctx['price'])) {
                            $ticketPrice = (float) $ctx['price'];
                        }
                        break;
                    }
                }
            }
        }

        if ($ticketTypeId === '') {
            $errors[] = __('סוג הכרטיס שנבחר אינו זמין', 'tapin');
            return null;
        }

        $clean['ticket_type'] = $ticketTypeId;
        $clean['ticket_type_label'] = $ticketTypeLabel !== '' ? $ticketTypeLabel : sanitize_text_field((string) ($attendee['ticket_type_label'] ?? ''));
        $clean['ticket_price'] = $ticketPrice;

        if (class_exists(TicketTypeTracer::class)) {
            TicketTypeTracer::sanitize($attendee, (string) $clean['ticket_type'], (float) $clean['ticket_price']);
        }

        $firstName = isset($clean['first_name']) ? (string) $clean['first_name'] : '';
        $lastName = isset($clean['last_name']) ? (string) $clean['last_name'] : '';
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $firstName !== '' ? $firstName : $lastName;
        }
        $clean['full_name'] = $fullName;

        return $clean;
    }

    public function missingFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf(__('חסר אימייל עבור %s.', 'tapin'), $attendeeLabel);
            case 'instagram':
                return sprintf(__('חסר שם משתמש אינסטגרם עבור %s.', 'tapin'), $attendeeLabel);
            case 'phone':
                return sprintf(__('חסר מספר טלפון עבור %s.', 'tapin'), $attendeeLabel);
            default:
                return sprintf(__('השדה %s הוא חובה עבור %s.', 'tapin'), $fieldLabel, $attendeeLabel);
        }
    }

    public function invalidFieldMessage(string $key, string $fieldLabel, string $attendeeLabel): string
    {
        switch ($key) {
            case 'email':
                return sprintf(__('כתובת האימייל של %s אינה תקינה.', 'tapin'), $attendeeLabel);
            case 'phone':
                return sprintf(__('מספר הטלפון של %s צריך \ליות \לין 9-10 ספרות.', 'tapin'), $attendeeLabel);
            case 'id_number':
                return sprintf(__('מספר הזהות \של %s צריך היות באורך 9 ספרות.', 'tapin'), $attendeeLabel);
            case 'instagram':
                return sprintf(__('שם משתמש אינסטגרם עבור %s לא תקין.', 'tapin'), $attendeeLabel);
            case 'tiktok':
                return sprintf(__('שם משתמש \טיקטוק עבור %s לא תקין.', 'tapin'), $attendeeLabel);
            case 'facebook':
                return sprintf(__('קישור פייסבוק \עעבור %s לא תקין.', 'tapin'), $attendeeLabel);
            default:
                return sprintf(__('הערך שסופק לשדה %s עבור %s אינו תקין.', 'tapin'), $fieldLabel, $attendeeLabel);
        }
    }

    public function attendeeLabel(int $index): string
    {
        return $index === 0 ? 'משלם ההזמנה' : sprintf('משתתף %d', $index + 1);
    }

    public function formatPrefillValue(string $fieldKey, string $value): string
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
}
