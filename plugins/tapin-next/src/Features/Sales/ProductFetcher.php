<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

final class ProductFetcher
{
    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,int> $authorCache
     */
    public function appendZeroSalesProducts(
        array &$rows,
        int $producerId,
        string $productStatus,
        EventRowFactory $factory,
        array &$authorCache
    ): void {
        $statusArg = strtolower($productStatus) === 'any'
            ? 'any'
            : array_filter(array_map('trim', explode(',', $productStatus)));

        $products = get_posts([
            'post_type'      => 'product',
            'author'         => $producerId,
            'post_status'    => $statusArg === 'any' ? 'any' : $statusArg,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        if (!is_array($products)) {
            return;
        }

        foreach ($products as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }
            if (!isset($rows[$productId])) {
                $authorId = $this->resolveAuthorId($productId, $authorCache) ?: $producerId;
                $rows[$productId] = $factory->create($productId, $authorId);
            } else {
                $factory->ensureCommissionMeta($rows[$productId], $productId);
            }
        }
    }

    /**
     * @param array<int,int> $authorCache
     */
    private function resolveAuthorId(int $productId, array &$authorCache): int
    {
        if (!array_key_exists($productId, $authorCache)) {
            $authorCache[$productId] = (int) get_post_field('post_author', $productId);
        }

        return $authorCache[$productId];
    }
}
