<?php

namespace App\Services\Marketplace;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class MarketplaceFoundationService
{
    public function map(int $listingId, ?int $variantId, ?int $productId, int $actorId, ?array $expected = null): void
    {
        DB::transaction(function () use ($listingId,$variantId,$productId,$actorId,$expected) {
            $storeId = DB::table('marketplace_listing_variants')->where('id', $listingId)->value('store_id');
            abort_unless($storeId, 404);
            // All mapping/unlink operations in a store serialize before conflict reads.
            abort_unless(DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first(), 404);
            abort_unless(DB::table('marketplace_listing_variants')->where('id', $listingId)->where('store_id', $storeId)->lockForUpdate()->first(), 404);
            if ($variantId !== null) {
                $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->first();
                abort_unless($variant && (int) $variant->product_id === $productId && $variant->enabled,422,'Select an enabled variant belonging to the selected JW product.');
                $conflict = DB::table('marketplace_variant_mappings as m')
                    ->join('marketplace_listing_variants as l', 'l.id', '=', 'm.listing_variant_id')
                    ->where('l.store_id', $storeId)
                    ->where('m.product_variant_id', $variantId)
                    ->where('m.listing_variant_id', '<>', $listingId)
                    ->select('m.id')->lockForUpdate()->first();
                abort_if($conflict, 409, 'This JW SKU is already mapped in this store. Unlink the existing mapping first.');
            }
            $old = DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listingId)->first();
            if($expected!==null) {
                $actual=$old?(int)$old->product_variant_id:null;
                abort_if($actual!==$expected['variant_id'],409,'Mapping changed after selection. Reload the catalog and review again.');
            }
            if (($old ? (int)$old->product_variant_id : null) === $variantId) return;
            if ($variantId === null) {
                DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listingId)->delete();
            } else {
                DB::table('marketplace_variant_mappings')->updateOrInsert(['listing_variant_id'=>$listingId],[
                    'store_id'=>$storeId,'product_variant_id'=>$variantId,'actor_id'=>$actorId,'updated_at'=>now(),'created_at'=>$old?->created_at ?? now(),
                ]);
            }
            DB::table('marketplace_mapping_events')->insert(['listing_variant_id'=>$listingId,'old_variant_id'=>$old?->product_variant_id,'new_variant_id'=>$variantId,'actor_id'=>$actorId,'created_at'=>now()]);
        });
    }
}
