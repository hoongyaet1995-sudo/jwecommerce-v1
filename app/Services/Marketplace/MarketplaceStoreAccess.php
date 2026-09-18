<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

final class MarketplaceStoreAccess
{
    public function publishingStore(int $storeId): object
    {
        abort_unless(config('marketplace.publish_operations_enabled')===true,403,'Marketplace publishing is disabled.');

        $store=DB::table('marketplace_stores')->where('id',$storeId)->first();
        $capability=$store
            ? DB::table('marketplace_store_capabilities')->where('store_id',$store->id)->first()
            : null;

        abort_unless($store && $store->status==='connected' && $capability && $capability->product_publish,
            403,'Product publishing is not enabled for this store.');

        return $store;
    }
}
