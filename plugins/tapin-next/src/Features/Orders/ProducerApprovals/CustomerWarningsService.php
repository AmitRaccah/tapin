<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

final class CustomerWarningsService
{
    private const LARGE_ORDER_THRESHOLD    = 10;
    private const CUSTOMER_TOTAL_THRESHOLD = 20;

    /**
     * @param array<string,array<string,mixed>> $stats
     * @return array<string,array<int,string>>
     */
    public function buildWarnings(array $stats): array
    {
        $warnings = [];

        foreach ($stats as $emailKey => $customer) {
            $largeOrders = array_filter(
                $customer['orders'],
                static fn($entry) => $entry['quantity'] >= self::LARGE_ORDER_THRESHOLD
            );

            if ($customer['total'] >= self::CUSTOMER_TOTAL_THRESHOLD || count($largeOrders) >= 2) {
                $name  = esc_html($customer['name'] ?: $customer['email']);
                $email = esc_html($customer['email']);
                $key   = strtolower(trim(is_string($emailKey) ? $emailKey : (string) $emailKey));
                if ($key === '') {
                    continue;
                }

                $warnings[$key][] = sprintf(
                    __('שים לב: %1$s (%2$s) רכש %3$d כרטיסים בסך הכול.', 'tapin'),
                    $name,
                    $email,
                    (int) $customer['total']
                );
            }
        }

        return $warnings;
    }

    /**
     * @param array<int,array<string,mixed>> $orders
     * @return array<string,array<int,array<int,string>>>
     */
    public function buildEventOrderWarnings(array $orders): array
    {
        $counts = [];

        foreach ($orders as $order) {
            $emailKey = strtolower(trim((string) ($order['customer']['email'] ?? '')));
            if ($emailKey === '') {
                continue;
            }

            $uniqueEventKeys = [];
            foreach ((array) ($order['events'] ?? []) as $eventData) {
                $eventId   = (int) ($eventData['event_id'] ?? 0);
                $productId = (int) ($eventData['product_id'] ?? 0);
                $eventKey  = $eventId ?: $productId;
                if ($eventKey <= 0) {
                    continue;
                }

                $uniqueEventKeys[$eventKey] = true;
            }

            if ($uniqueEventKeys === []) {
                continue;
            }

            foreach (array_keys($uniqueEventKeys) as $eventKey) {
                $counts[$emailKey][$eventKey] = ($counts[$emailKey][$eventKey] ?? 0) + 1;
            }
        }

        $warnings = [];
        foreach ($counts as $emailKey => $eventCounts) {
            foreach ($eventCounts as $eventKey => $count) {
                if ($count < 3) {
                    continue;
                }

                $warnings[$emailKey][(int) $eventKey][] = __('אזהרה: ללקוח הזה יש יותר משתי הזמנות לאירוע זה.', 'tapin');
            }
        }

        return $warnings;
    }
}

