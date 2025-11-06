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

                $warnings[$key][] = sprintf('&#1513;&#1497;&#1501;&#32;&#1500;&#1489;: %1$s (%2$s) &#1512;&#1499;&#1513;&#32;%3$d&#32;&#1499;&#1512;&#1496;&#1497;&#1505;&#1497;&#1501;&#32;&#1489;&#1505;&#1495;&#32;&#1499;&#1493;&#1500;.', $name, $email, (int) $customer['total']);
            }
        }

        return $warnings;
    }
}

