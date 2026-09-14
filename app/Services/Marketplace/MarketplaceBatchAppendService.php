<?php

namespace App\Services\Marketplace;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class MarketplaceBatchAppendService
{
    public function append(int $listingId, int $destination, int $actor, array $reviewedIds, array $skus): array
    {
        return DB::transaction(function () use ($listingId, $destination, $actor, $reviewedIds, $skus) {
            $source = DB::table('marketplace_listing_variants')->find($listingId);
            abort_unless($source, 404);
            DB::table('marketplace_stores')->where('id', $source->store_id)->lockForUpdate()->first();
            $listings = DB::table('marketplace_listing_variants')->where('store_id', $source->store_id)
                ->where('external_product_id', $source->external_product_id)->orderBy('id')->lockForUpdate()->get();
            $ids = $listings->pluck('id')->map(fn ($id) => (int) $id)->all();
            $reviewedIds = array_map('intval', $reviewedIds);
            sort($reviewedIds);
            abort_unless($ids === $reviewedIds, 409, 'The store variant list changed. Refresh and review the complete product.');
            abort_if(array_diff(array_map('intval', array_keys($skus)), $ids), 422, 'SKU overrides must belong to this product.');
            $parent = Product::whereKey($destination)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($parent->status, ['draft', 'active'], true), 409, 'Select a draft or active destination product.');
            $result = ['productId'=>$destination, 'status'=>$parent->status, 'createdVariants'=>0, 'linkedVariants'=>0, 'keptVariants'=>0];
            foreach ($listings as $listing) {
                $mapping = DB::table('marketplace_variant_mappings')->where('listing_variant_id', $listing->id)->first();
                if ($mapping) {
                    $mapped = ProductVariant::whereKey($mapping->product_variant_id)->lockForUpdate()->firstOrFail();
                    abort_unless((int) $mapped->product_id === $destination, 409,
                        'SKU '.$mapped->sku.' is mapped to Product #'.$mapped->product_id.'. Review its ownership separately.');
                    abort_unless($mapped->enabled, 409, 'An existing mapped variant is disabled. Review it before appending.');
                    $result['keptVariants']++;
                    continue;
                }
                $identity = DB::table('marketplace_variant_imports')->where('listing_variant_id', $listing->id)->first();
                if ($identity) {
                    $existing = ProductVariant::whereKey($identity->product_variant_id)->lockForUpdate()->firstOrFail();
                } else {
                    $data = json_decode($listing->observed_data, true, 512, JSON_THROW_ON_ERROR);
                    $existing = ProductVariant::where('sku', trim($data['sku'] ?? ''))->lockForUpdate()->first();
                    if (!$existing && isset($skus[$listing->id])) {
                        $existing = ProductVariant::where('sku', trim($skus[$listing->id]))->lockForUpdate()->first();
                    }
                }
                if ($existing) {
                    abort_unless((int) $existing->product_id === $destination, 409,
                        'SKU '.$existing->sku.' belongs to Product #'.$existing->product_id.'. No variants were appended; review its ownership separately.');
                    app(MarketplaceFoundationService::class)->map($listing->id, $existing->id, $destination, $actor);
                    $result['linkedVariants']++;
                } else {
                    app(MarketplaceImportService::class)->importVariant($listing->id, $destination, $actor, $skus[$listing->id] ?? null);
                    $result['createdVariants']++;
                }
            }
            return $result;
        });
    }
}
