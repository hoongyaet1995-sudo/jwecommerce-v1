<?php

namespace App\Services\Marketplace;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class MarketplaceFoundationService
{
    public function map(int $listingId, ?int $variantId, ?int $productId, int $actorId): void
    {
        DB::transaction(function () use ($listingId,$variantId,$productId,$actorId) {
            abort_unless(DB::table('marketplace_listing_variants')->where('id',$listingId)->lockForUpdate()->first(),404);
            if ($variantId !== null) {
                $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->first();
                abort_unless($variant && (int) $variant->product_id === $productId && $variant->enabled,422,'Select an enabled variant belonging to the selected JW product.');
            }
            $old = DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listingId)->first();
            if (($old ? (int)$old->product_variant_id : null) === $variantId) return;
            if ($variantId === null) {
                DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listingId)->delete();
            } else {
                DB::table('marketplace_variant_mappings')->updateOrInsert(['listing_variant_id'=>$listingId],[
                    'product_variant_id'=>$variantId,'actor_id'=>$actorId,'updated_at'=>now(),'created_at'=>$old?->created_at ?? now(),
                ]);
            }
            DB::table('marketplace_mapping_events')->insert(['listing_variant_id'=>$listingId,'old_variant_id'=>$old?->product_variant_id,'new_variant_id'=>$variantId,'actor_id'=>$actorId,'created_at'=>now()]);
        });
    }
}
