<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Services\Inventory\WebsiteInventoryAvailability;
use Illuminate\Support\Collection;

/** Explicit public projection shared by PDP and Quick Buy. */
class StorefrontVariantData
{
    public function __construct(private WebsiteInventoryAvailability $inventory) {}

    public function payload(Collection $variants, Collection $images): Collection
    {
        return $variants->values()->map(function (ProductVariant $variant) use ($images) {
            $linked = $images->firstWhere('product_variant_id', $variant->id);
            return [
                'id' => (string) $variant->id, 'sku' => $variant->sku, 'title' => $variant->title,
                'price' => (int) $variant->price,
                'compareAt' => $variant->compare_at_price ? (int) $variant->compare_at_price : null,
                'stock' => $this->inventory->quantity($variant), 'enabled' => $variant->enabled !== false,
                'options' => is_array($variant->options) ? $variant->options : [],
                'image' => $variant->image_url ?: $linked?->path,
                'visualType' => $variant->visual_type, 'colorCode' => $variant->color_code ?: $variant->color_hex,
                'optionImage' => $variant->option_image ?: $linked?->path,
            ];
        });
    }

    public function select(Collection $variants, ?int $requested): ?ProductVariant
    {
        return $variants->firstWhere('id', $requested)
            ?? $variants->first(fn ($variant) => $this->canAdd($variant))
            ?? $variants->first();
    }

    public function canAdd(?ProductVariant $variant): bool
    {
        return $variant && $variant->enabled !== false && $this->inventory->quantity($variant) > 0;
    }
}
