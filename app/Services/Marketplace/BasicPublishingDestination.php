<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;

/** Local eligibility only; transport must still confirm the provider identity. */
final class BasicPublishingDestination
{
    public function check(int $storeId, int $productId): void
    {
        if (config('marketplace.basic_wms_enabled') !== true) return;
        $store=DB::table('marketplace_stores')->where('id',$storeId)->first();
        abort_unless($store && in_array($store->platform,['shopee','lazada'],true)
            && $store->status==='connected' && $store->data_classification==='test'
            && $store->classified_by && $store->classified_at
            && (string)config('marketplace.'.$store->platform.'.test_store_id')===(string)$storeId,
            403,'Publishing requires the designated classified test store.');
        $token=MarketplaceStoreToken::where('store_id',$storeId)->first();
        if ($store->platform==='shopee') {
            abort_unless($store->environment==='sandbox' && config('marketplace.shopee.environment')==='sandbox'
                && $token && $token->application_id===(string)config('marketplace.shopee.partner_id'),403,'Shopee sandbox credentials required.');
        } else {
            abort_unless($store->environment==='live' && $store->region==='MY'
                && $store->external_shop_id==='300163725140' && (string)config('marketplace.lazada.app_key')==='139209'
                && $token?->application_id==='139209' && $token->credential_source==='official_test'
                && $token->test_lease_expires_at?->isFuture()
                && LazadaOfficialTestAccess::environmentAllows($store,$token->application_id),403,'Verified Lazada test credentials required.');
        }
        $variants=DB::table('product_variants')->where('product_id',$productId)->where('enabled',true)->get();
        abort_unless($variants->isNotEmpty() && $variants->every(fn($variant)=>$variant->data_classification==='test'
            && $variant->classified_by && $variant->classified_at),403,'Publishing requires classified test variants.');
    }
}
