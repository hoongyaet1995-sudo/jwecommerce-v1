<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use App\Services\Marketplace\MarketplaceStoreAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopeeSingaporeReadSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sg_order_without_provider_currency_is_not_saved_as_an_operational_order(): void
    {
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Store', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        try {
            app(MarketplaceOrderSnapshotService::class)->saveShopee($storeId, [
                'order_sn' => 'SG-1', 'order_status' => 'UNPAID', 'update_time' => 100,
                'total_amount' => 19.90,
            ]);
        } finally {
            $this->assertDatabaseCount('marketplace_orders', 0);
        }
    }

    public function test_production_store_uses_the_same_publishing_access_policy_when_enabled(): void
    {
        config(['marketplace.publish_operations_enabled' => true]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Store', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_capabilities')->insert([
            'store_id' => $storeId, 'catalog_read' => true, 'product_publish' => true,
            'order_receive' => false, 'inventory_update' => false, 'fulfillment_write' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame($storeId, app(MarketplaceStoreAccess::class)->publishingStore($storeId)->id);
    }

    public function test_connected_sg_sandbox_store_can_enter_publishing_with_its_own_capability(): void
    {
        config(['marketplace.publish_operations_enabled' => true]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'sandbox', 'external_shop_id' => '227929253',
            'shop_name' => 'SG Sandbox', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_capabilities')->insert([
            'store_id' => $storeId, 'catalog_read' => true, 'product_publish' => true,
            'order_receive' => false, 'inventory_update' => false, 'fulfillment_write' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame($storeId, app(MarketplaceStoreAccess::class)->publishingStore($storeId)->id);
    }
}
