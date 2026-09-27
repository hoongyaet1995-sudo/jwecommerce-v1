<?php

namespace App\Services\Marketplace;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
        abort_if($store->platform === 'shopee' && $store->environment !== 'sandbox',
            403, 'Shopee production publishing is not enabled for this workflow.');

        return $store;
    }

    public function setProductPublishing(int $storeId, bool $enabled, int $actorId, string $reason): void
    {
        $reason=trim($reason);
        if ($reason==='' || mb_strlen($reason)>255) {
            throw new RuntimeException('A capability-change reason is required.');
        }
        if (!User::whereKey($actorId)->where('is_admin',true)->exists()) {
            throw new RuntimeException('An administrator is required.');
        }

        DB::transaction(function () use ($storeId,$enabled,$actorId,$reason): void {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if (!$store) {
                throw new RuntimeException('Marketplace store was not found.');
            }

            $capability=DB::table('marketplace_store_capabilities')->where('store_id',$storeId)->lockForUpdate()->first();
            $changes=['product_publish'=>$enabled,'changed_by'=>$actorId,'change_reason'=>$reason,'updated_at'=>now()];
            if ($capability) {
                DB::table('marketplace_store_capabilities')->where('store_id',$storeId)->update($changes);
            } else {
                DB::table('marketplace_store_capabilities')->insert($changes+[
                    'store_id'=>$storeId,'catalog_read'=>true,'order_receive'=>false,
                    'inventory_update'=>false,'fulfillment_write'=>false,'created_at'=>now(),
                ]);
            }
        });
    }
}
