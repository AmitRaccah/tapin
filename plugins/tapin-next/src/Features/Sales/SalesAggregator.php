<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

use Tapin\Events\Features\Orders\PartiallyApprovedStatus;
use Tapin\Events\Integrations\Affiliate\ReferralsRepository;
use Tapin\Events\Support\Commission;
use WC_DateTime;
use WC_Order;
use WC_Order_Item_Product;

final class SalesAggregator
{
    /**
     * @param array<int,int> $orderIds
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    public function aggregate(array $orderIds, int $producerId, int $affiliateId, array $options = []): array
    {
        if (!function_exists('wc_get_order')) {
            return [];
        }

        $rows = [];
        $thumbCache = [];
        $eventTsCache = [];
        $authorCache = [];
        $commissionCache = [];
        $referralCache = [];

        $referrals = new ReferralsRepository();
        $windows = new WindowsBuckets();
        $factory = new EventRowFactory($thumbCache, $eventTsCache, $commissionCache, $windows);
        $accum = new TicketStatsAccumulator($windows);
        $authorResolver = new AuthorResolver();
        $fetcher = new ProductFetcher($authorResolver);

        $includeZero = !empty($options['include_zero']);
        $productStatus = isset($options['product_status']) ? (string) $options['product_status'] : 'publish';

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId <= 0) {
                continue;
            }
            $order = wc_get_order($orderId);
            if (!$order instanceof WC_Order) {
                continue;
            }
            $orderTs = $this->resolveOrderTimestamp($order);
            $wasReferred = $referrals->hasReferral($orderId, $affiliateId, $referralCache);
            $isPartial = $order->has_status(PartiallyApprovedStatus::STATUS_SLUG);
            $partialMap = [];
            if ($isPartial) {
                $rawMap = $order->get_meta('_tapin_partial_approved_map', true);
                if (is_array($rawMap)) {
                    foreach ($rawMap as $rawItemId => $approvedQty) {
                        $itemKey = (int) $rawItemId;
                        if ($itemKey <= 0) {
                            continue;
                        }
                        $partialMap[$itemKey] = (int) $approvedQty;
                    }
                }
            }

            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $productId = (int) $item->get_product_id();
                if ($productId <= 0) {
                    continue;
                }

                $authorId = $authorResolver->resolve($productId, $authorCache);
                if ($authorId !== $producerId) {
                    continue;
                }
                $itemId = (int) $item->get_id();
                $quantity = (int) $item->get_quantity();
                $lineTotal = (float) $item->get_total();
                $qty = $quantity;

                if ($isPartial && $itemId > 0 && array_key_exists($itemId, $partialMap)) {
                    $approved = min($quantity, max(0, (int) $partialMap[$itemId]));
                    if ($approved <= 0) {
                        continue;
                    }
                    $unit = $quantity > 0 ? ($lineTotal / max(1, $quantity)) : $lineTotal;
                    $qty = $approved;
                    $lineTotal = $unit * $approved;
                }

                if (!isset($rows[$productId])) {
                    $rows[$productId] = $factory->create($productId, $authorId);
                } else {
                    $factory->ensureCommissionMeta($rows[$productId], $productId);
                }

                $rows[$productId]['qty'] += $qty;
                $rows[$productId]['sum'] += $lineTotal;

                if ($wasReferred) {
                    $rows[$productId]['ref_qty'] += $qty;
                    $rows[$productId]['ref_sum'] += $lineTotal;

                    $commission = Commission::calculate($rows[$productId]['commission_meta'] ?? [], $lineTotal, $qty);
                    if ($commission > 0) {
                        $rows[$productId]['ref_commission'] += $commission;
                    }
                }

                $accum->accumulate($rows[$productId], $item, $wasReferred, $orderTs);
            }
        }

        if ($includeZero) {
            $fetcher->appendZeroSalesProducts(
                $rows,
                $producerId,
                $productStatus,
                $factory,
                $authorCache
            );
        }

        uasort($rows, static function (array $a, array $b): int {
            $created = ($b['created_ts'] ?? 0) <=> ($a['created_ts'] ?? 0);
            if ($created !== 0) {
                return $created;
            }

            $event = ($b['event_ts'] ?? 0) <=> ($a['event_ts'] ?? 0);
            if ($event !== 0) {
                return $event;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    private function resolveOrderTimestamp(WC_Order $order): int
    {
        $created = $order->get_date_created();
        if ($created instanceof WC_DateTime) {
            return $created->getTimestamp();
        }
        return 0;
    }

}
