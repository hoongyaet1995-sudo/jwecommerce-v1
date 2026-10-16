<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Services\Inventory\WebsiteInventoryAvailability;
use Illuminate\Support\Collection;

/** Explicit public projection shared by PDP and Quick Buy. */
class StorefrontVariantData
{
    public function __construct(private WebsiteInventoryAvailability $inventory) {}

    public function payload(Collection $variants, Collection $images, ?\App\Models\Product $product = null): Collection
    {
        return $variants->values()->map(function (ProductVariant $variant) use ($images, $product) {
            $owner = $product ?? $variant->product;
            $showPrice = $owner && app(ProductPurchaseRules::class)->showsPrice($owner);
            $linked = $images->firstWhere('product_variant_id', $variant->id);
            return [
                'id' => (string) $variant->id, 'sku' => $variant->sku, 'title' => $variant->title,
                'price' => $showPrice ? (int) $variant->price : null,
                'compareAt' => $owner?->isBuyMode() && $variant->compare_at_price ? (int) $variant->compare_at_price : null,
                'stock' => $owner?->isBuyMode() ? $this->inventory->quantity($variant) : null, 'enabled' => $variant->enabled !== false,
                'options' => is_array($variant->options) ? $variant->options : [],
                'image' => $variant->image_url ?: $linked?->path,
                'visualType' => $variant->visual_type, 'colorCode' => $variant->color_code ?: $variant->color_hex,
                'optionImage' => $variant->option_image ?: $linked?->path,
            ];
        });
    }

    public function select(Collection $variants, ?int $requested, ?\App\Models\Product $product = null): ?ProductVariant
    {
        return $variants->firstWhere('id', $requested)
            ?? $variants->first(fn ($variant) => $this->canAdd($variant, $product))
            ?? $variants->first();
    }

    public function canAdd(?ProductVariant $variant, ?\App\Models\Product $product = null): bool
    {
        if (! $variant) return false;
        $owner = $product ?? $variant->product;
        return $owner?->isBuyMode() && (! $owner->exists || $owner->status === 'active') && $variant->enabled !== false && $this->inventory->quantity($variant) > 0;
    }
}
