<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Domain\SaleWindowsRepository;

final class WindowsBuckets
{
    /**
     * @param array<int,array<string,mixed>> $ticketTypes
     * @return array<int,array<string,int>>
     */
    public function build(int $productId, array $ticketTypes): array
    {
        $windows = SaleWindowsRepository::get($productId, $ticketTypes);
        $buckets = [];
        foreach ($windows as $window) {
            $buckets[] = [
                'start'     => (int) ($window['start'] ?? 0),
                'end'       => (int) ($window['end'] ?? 0),
                'affiliate' => 0,
                'direct'    => 0,
            ];
        }

        return $buckets;
    }

    /**
     * @param array<int,array<string,int>> $windows
     */
    public function increment(array &$windows, int $orderTs, bool $wasReferred): void
    {
        if ($orderTs <= 0 || !is_array($windows)) {
            return;
        }

        foreach ($windows as &$window) {
            if (!is_array($window)) {
                continue;
            }

            $start = (int) ($window['start'] ?? 0);
            $end = (int) ($window['end'] ?? 0);
            if ($this->timestampInWindow($orderTs, $start, $end)) {
                $key = $wasReferred ? 'affiliate' : 'direct';
                $window[$key] = (int) ($window[$key] ?? 0) + 1;
                break;
            }
        }
        unset($window);
    }

    private function timestampInWindow(int $ts, int $start, int $end): bool
    {
        if ($ts <= 0) {
            return false;
        }
        if ($start > 0 && $ts < $start) {
            return false;
        }
        if ($end > 0 && $ts > $end) {
            return false;
        }
        return true;
    }
}
