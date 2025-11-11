<?php
declare(strict_types=1);

namespace Tapin\Events\Features\Sales;

final class AuthorResolver
{
    /**
     * @param array<int,int> $cache
     */
    public function resolve(int $productId, array &$cache): int
    {
        if (!array_key_exists($productId, $cache)) {
            $cache[$productId] = (int) get_post_field('post_author', $productId);
        }

        return $cache[$productId];
    }
}
