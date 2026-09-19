<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;

/** Local eligibility only; transport must still confirm the provider identity. */
final class BasicPublishingDestination
{
    public function check(int $storeId, int $productId): void
    {
        $store=app(MarketplaceStoreAccess::class)->publishingStore($storeId);

        if (config('marketplace.basic_wms_enabled') !== true) return;
        abort_unless($store && in_array($store->platform,['shopee','lazada'],true)
            && $store->status==='connected',
            403,'Connect the destination store before publishing.');
        $token=MarketplaceStoreToken::where('store_id',$storeId)->first();
        if ($store->platform==='shopee') {
            abort_unless($store->environment==='sandbox' && config('marketplace.shopee.environment')==='sandbox'
                && $token && $token->application_id===(string)config('marketplace.shopee.partner_id'),403,'Shopee sandbox credentials required.');
        } else {
            abort_unless($store->environment==='live' && $store->region==='MY'
                && (($token?->credential_source==='oauth' && $token->application_id===(string)config('marketplace.lazada.app_key'))
                    || ($token?->credential_source==='official_test'
                    && $token->application_id===(string)config('marketplace.lazada.test_app_key')
                    && $token->test_lease_expires_at?->isFuture()
                    && LazadaOfficialTestAccess::environmentAllows($store,$token->application_id))),
                403,'Verified Lazada credentials required.');
        }
        abort_unless(DB::table('product_variants')->where('product_id',$productId)->where('enabled',true)->exists(),
            422,'Enable at least one product variant before publishing.');
    }
}
