<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class MarketplaceStoreAccessTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations { setUp as private migrateIsolatedSetUp; }

    protected function setUp(): void
    {
        $this->migrateIsolatedSetUp();

        if (!Schema::hasColumn('marketplace_stores','account_mode')) {
            (require __DIR__.'/../../database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php')->up();
        }
    }

    public function test_capabilities_are_isolated_per_store_and_default_to_read_only(): void
    {
        $first=$this->connectedStore('300927968304');
        $second=$this->connectedStore('300927968305');

        DB::table('marketplace_store_capabilities')->where('store_id',$first)->update([
            'product_publish'=>true,'change_reason'=>'Acceptance',
        ]);

        $this->assertTrue((bool)DB::table('marketplace_store_capabilities')->where('store_id',$first)->value('product_publish'));
        $this->assertFalse((bool)DB::table('marketplace_store_capabilities')->where('store_id',$second)->value('product_publish'));
    }

    private function connectedStore(string $shopId): int
    {
        $storeId=DB::table('marketplace_stores')->insertGetId([
            'platform'=>'lazada','shop_name'=>'Lazada '.$shopId,'external_shop_id'=>$shopId,
            'region'=>'MY','environment'=>'live','account_mode'=>'production','status'=>'connected',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('marketplace_store_capabilities')->insert([
            'store_id'=>$storeId,'catalog_read'=>true,'product_publish'=>false,
            'order_receive'=>false,'inventory_update'=>false,'fulfillment_write'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $storeId;
    }
}
