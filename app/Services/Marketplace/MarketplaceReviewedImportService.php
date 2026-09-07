<?php

namespace App\Services\Marketplace;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class MarketplaceReviewedImportService
{
    public function __construct(
        private MarketplaceImportService $imports,
        private MarketplaceFoundationService $mappings,
    ) {}

    public function import(int $storeId, string $externalProductId, int $actorId, array $skuOverrides, array $links): array
    {
        return DB::transaction(function () use ($storeId, $externalProductId, $actorId, $skuOverrides, $links) {
            abort_unless(DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first(), 404);
            $receipt = DB::table('marketplace_reviewed_imports')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->first();
            if ($receipt) return json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR);

            $listings = DB::table('marketplace_listing_variants')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->orderBy('id')->lockForUpdate()->get();
            abort_if($listings->isEmpty(), 404);
            $ids = $listings->pluck('id')->map(fn ($id) => (string) $id)->all();
            foreach (array_merge(array_keys($skuOverrides), array_keys($links)) as $id) {
                abort_unless(in_array((string) $id, $ids, true), 422, 'Review contains a variant from another product.');
            }
            abort_if(array_intersect_key($skuOverrides, $links), 422, 'Choose either an existing SKU or a new SKU code.');
            abort_if(DB::table('marketplace_product_imports')->where('store_id', $storeId)
                ->where('external_product_id', $externalProductId)->exists(), 409, 'This product was already imported.');
            abort_if(DB::table('marketplace_variant_imports')->whereIn('listing_variant_id', $ids)->exists()
                || DB::table('marketplace_variant_mappings')->whereIn('listing_variant_id', $ids)->exists(),
                409, 'A variant was already imported or linked. Refresh the review.');

            $result = ['productId'=>null, 'createdVariants'=>0, 'linkedVariants'=>0];
            foreach ($listings as $listing) {
                if (array_key_exists($listing->id, $links)) {
                    $target = $links[$listing->id];
                    abort_unless(is_array($target) && isset($target['productId'], $target['variantId'])
                        && is_int($target['productId']) && $target['productId'] > 0
                        && is_int($target['variantId']) && $target['variantId'] > 0,
                        422, 'Select a valid existing product and variant.');
                    $this->mappings->map($listing->id, $target['variantId'], $target['productId'], $actorId);
                    $result['linkedVariants']++;
                    continue;
                }
                $sku = $skuOverrides[$listing->id] ?? null;
                abort_unless($sku === null || (is_string($sku) && trim($sku) !== '' && mb_strlen($sku) <= 255),
                    422, 'Review the new SKU code.');
                if ($result['productId'] === null) {
                    $variantId = $this->imports->importVariantAsProduct($listing->id, $actorId, $sku);
                    $result['productId'] = (int) ProductVariant::findOrFail($variantId)->product_id;
                } else {
                    $this->imports->importVariant($listing->id, $result['productId'], $actorId, $sku);
                }
                $result['createdVariants']++;
            }
            DB::table('marketplace_reviewed_imports')->insert([
                'store_id'=>$storeId, 'external_product_id'=>$externalProductId, 'actor_id'=>$actorId,
                'result'=>json_encode($result, JSON_THROW_ON_ERROR), 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            return $result;
        });
    }
}
