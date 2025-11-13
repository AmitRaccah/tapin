<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

final class SalesQuery
{
    /**
     * @param array{from?:string,to?:string,statuses?:string} $args
     * @return array<int,int>
     */
    public function resolveOrderIds(array $args): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $from = isset($args['from']) ? sanitize_text_field((string) $args['from']) : '';
        $to = isset($args['to']) ? sanitize_text_field((string) $args['to']) : '';
        $statusesRaw = isset($args['statuses']) ? (string) $args['statuses'] : 'processing,completed';

        $dateAfter = $this->normalizeDateBound($from, '00:00:00');
        $dateBefore = $this->normalizeDateBound($to, '23:59:59');
        $statuses = $this->normalizeStatuses($statusesRaw);

        $queryArgs = [
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
            'status'  => $statuses !== [] ? $statuses : ['wc-processing', 'wc-completed'],
            'return'  => 'ids',
        ];

        if ($dateAfter || $dateBefore) {
            $queryArgs['date_created'] = array_filter([
                'after'     => $dateAfter ?: null,
                'before'    => $dateBefore ?: null,
                'inclusive' => true,
            ]);
        }

        $orders = wc_get_orders($queryArgs);
        if (!is_array($orders)) {
            return [];
        }

        return array_values(array_map('intval', $orders));
    }

    private function normalizeDateBound(string $value, string $timeSuffix): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date_i18n("Y-m-d {$timeSuffix}", $timestamp);
    }

    /**
     * @return array<int,string>
     */
    private function normalizeStatuses(string $statuses): array
    {
        $parts = array_map('trim', explode(',', $statuses));
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $key = sanitize_key($part);
            if ($key === '') {
                continue;
            }
            if (strpos($key, 'wc-') !== 0) {
                $key = 'wc-' . $key;
            }
            $normalized[] = $key;
        }

        return array_values(array_unique($normalized));
    }
}
