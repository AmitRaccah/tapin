<?php

namespace Tapin\Events\Support;

final class TicketTypeTracer
{
    public static function enabled(): bool
    {
        return defined('TAPIN_TICKET_DEBUG') && TAPIN_TICKET_DEBUG;
    }

    private static function log(string $message): void
    {
        if (!self::enabled()) {
            return;
        }
        error_log('[tapin_tickets] ' . $message);
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     */
    public static function ensure(array $entries): void
    {
        if (!self::enabled()) {
            return;
        }
        foreach ($entries as $row) {
            $id        = isset($row['id']) ? (string) $row['id'] : '';
            $name      = isset($row['name']) ? (string) $row['name'] : '';
            $price     = isset($row['price']) ? (float) $row['price'] : 0.0;
            $available = isset($row['available']) ? (int) $row['available'] : 0;
            $capacity  = isset($row['capacity']) ? (int) $row['capacity'] : 0;
            self::log(sprintf(
                'ensureTicketTypeCache: id=%s name=%s price=%0.2f available=%d capacity=%d',
                $id,
                $name,
                $price,
                $available,
                $capacity
            ));
        }
    }

    /**
     * @param array<string,mixed> $raw
     */
    public static function sanitize(array $raw, string $resolvedId, float $resolvedPrice): void
    {
        if (!self::enabled()) {
            return;
        }
        $rawId    = isset($raw['ticket_type']) ? (string) $raw['ticket_type'] : '';
        $rawLabel = isset($raw['ticket_type_label']) ? (string) $raw['ticket_type_label'] : '';
        self::log(sprintf(
            'sanitizeAttendee: raw.id=%s raw.label=%s -> id=%s price=%0.2f',
            $rawId,
            $rawLabel,
            $resolvedId,
            $resolvedPrice
        ));
    }

    public static function attach(bool $generated, string $typeId, ?float $price, string $attendeesKey): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'attachCartItemData: generated=%s type.id=%s price=%s key=%s',
            $generated ? 'true' : 'false',
            $typeId,
            $price !== null ? number_format($price, 2, '.', '') : '',
            $attendeesKey
        ));
    }

    public static function apply(string $cartKey, ?float $incomingItemPrice, ?float $incomingAttendeePrice, ?float $finalPrice): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'applyAttendeePricing: key=%s incoming_item=%s incoming_attendee=%s final=%s',
            $cartKey,
            $incomingItemPrice !== null ? number_format($incomingItemPrice, 2, '.', '') : '',
            $incomingAttendeePrice !== null ? number_format($incomingAttendeePrice, 2, '.', '') : '',
            $finalPrice !== null ? number_format($finalPrice, 2, '.', '') : ''
        ));
    }

    public static function applyDetailed(string $cartKey, int $productId, string $typeId, string $source, ?float $incomingItemPrice, ?float $incomingAttendeePrice, ?float $finalPrice): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'applyAttendeePricing: key=%s product_id=%d type_id=%s source=%s incoming_item=%s incoming_attendee=%s final=%s',
            $cartKey,
            $productId,
            $typeId,
            $source,
            $incomingItemPrice !== null ? number_format($incomingItemPrice, 2, '.', '') : '',
            $incomingAttendeePrice !== null ? number_format($incomingAttendeePrice, 2, '.', '') : '',
            $finalPrice !== null ? number_format($finalPrice, 2, '.', '') : ''
        ));
    }

    public static function validate(int $productId, int $originalQty, int $decodedCount, int $sanitizedCount): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'validateSubmission: product_id=%d qty_in=%d decoded=%d sanitized=%d qty_forced=1',
            $productId,
            $originalQty,
            $decodedCount,
            $sanitizedCount
        ));
    }

    public static function resume(int $productId, int $attendeesCount, bool $added): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'maybeResumePendingCheckout: product_id=%d attendees=%d added_to_cart=%s',
            $productId,
            $attendeesCount,
            $added ? 'true' : 'false'
        ));
    }

    public static function attachQueueState(bool $processingSplitAdd, bool $isGenerated, string $takenTypeId, int $remainingCount): void
    {
        if (!self::enabled()) {
            return;
        }
        self::log(sprintf(
            'attachCartItemData: processingSplitAdd=%s isGenerated=%s taken_type_id=%s remaining_in_queue=%d',
            $processingSplitAdd ? 'true' : 'false',
            $isGenerated ? 'true' : 'false',
            $takenTypeId,
            $remainingCount
        ));
    }
}
