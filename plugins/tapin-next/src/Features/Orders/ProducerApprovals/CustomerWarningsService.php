<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Orders\ProducerApprovals;

use Tapin\Events\Features\Orders\ProducerApprovals\Utils\Html;

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

                $warnings[$key][] = sprintf('&#1513;&#1497;&#1501;&#32;&#1500;&#1489;: %1$s (%2$s) &#1512;&#1499;&#1513;&#32;%3$d&#32;&#1499;&#1512;&#1496;&#1497;&#1505;&#1497;&#1501;&#32;&#1489;&#1505;&#1495;&#32;&#1499;&#1493;&#1500;.', $name, $email, (int) $customer['total']);
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

                $warnings[$emailKey][(int) $eventKey][] = Html::decodeEntities('&#1488;&#1494;&#1492;&#1512;&#1492;&#58;&#32;&#1500;&#1500;&#1511;&#1493;&#1495;&#32;&#1492;&#1494;&#1492;&#32;&#1497;&#1513;&#32;&#1497;&#1493;&#1514;&#1512;&#32;&#1502;&#1513;&#1514;&#1497;&#32;&#1492;&#1494;&#1502;&#1504;&#1493;&#1514;&#32;&#1500;&#1488;&#1497;&#1512;&#1493;&#1506;&#32;&#1494;&#1492;');
            }
        }

        return $warnings;
    }
}

