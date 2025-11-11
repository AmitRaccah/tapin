<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

final class ProductFetcher
{
    private AuthorResolver $authorResolver;

    public function __construct(?AuthorResolver $authorResolver = null)
    {
        $this->authorResolver = $authorResolver ?? new AuthorResolver();
    }

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
                $authorId = $this->authorResolver->resolve($productId, $authorCache) ?: $producerId;
                $rows[$productId] = $factory->create($productId, $authorId);
            } else {
                $factory->ensureCommissionMeta($rows[$productId], $productId);
            }
        }
    }
}
