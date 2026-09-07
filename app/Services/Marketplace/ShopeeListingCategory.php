<?php

namespace App\Services\Marketplace;

final class ShopeeListingCategory
{
    /** Input must be freshly fetched server-side for the destination store, never browser metadata. */
    public static function requireLeaf(array $categories, int $categoryId): int
    {
        $matches=array_values(array_filter($categories,fn ($row)=>is_array($row)
            && ($row['category_id'] ?? null)===$categoryId));
        if ($categoryId<=0 || count($matches)!==1 || ($matches[0]['has_children'] ?? null)!==false) {
            throw new \DomainException('Select an available leaf category from this store before publishing.');
        }
        return $categoryId;
    }
}
