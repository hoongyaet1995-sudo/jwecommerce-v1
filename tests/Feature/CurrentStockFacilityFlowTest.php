<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use App\Services\Inventory\StoreManagement\FacilityBalanceReader;
use App\Services\Inventory\StoreManagement\OverviewReader;
use App\Services\Inventory\StoreManagement\FacilityTransferReview;
use App\Services\Inventory\CurrentStockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CurrentStockFacilityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_first_count_intake_and_audit_change_only_current_stock(): void
    {
        Queue::fake();
        config(['inventory.current_stock_only' => true,
            'marketplace.multiwarehouse_operations_enabled' => true,
            'marketplace.multiwarehouse_stock_sync_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $product = Product::create(['title' => 'Facility Current Stock', 'handle' => 'facility-current-stock', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-FAC', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform' => 'lazada',
            'shop_name' => 'Current Stock Test', 'external_shop_id' => 'CS-FAC-STORE',
            'region' => 'MY', 'environment' => 'test', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now()]);
        app(FacilityRegistry::class)->confirm($store, [['provider_id' => 'CS-WH', 'name' => 'Test Warehouse']]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store)->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'CS-WH', 'name' => 'Test Warehouse', 'is_active' => true,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store, 'external_product_id' => 'CS-P', 'external_variant_id' => 'CS-V',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store, 'listing_variant_id' => $listing, 'product_variant_id' => $variant->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $stock = app(FacilityStockReview::class);
        foreach ([['count', 10, 10], ['intake', 2, 12], ['audit', 8, 8]] as [$kind, $input, $expected]) {
            $review = $stock->review($actor, $store, $facility->id, $variant->id, $kind, $input, 'Test');
            $receipt = $stock->confirm($actor, $review['id'], 'current-'.$kind);
            $this->assertSame($expected, $receipt['after']['current_stock']);
        }
        $balance = DB::table('inventory_balances')->where('product_variant_id', $variant->id)->first();
        $this->assertSame(8, (int) $balance->current_stock);
        $this->assertNull($balance->physical_stock);
        $facilityRead = app(FacilityBalanceReader::class)->read($store, $variant->id);
        $this->assertSame(8, $facilityRead['facilities'][0]['current_stock']);
        $overview = app(OverviewReader::class)->read(['sku' => 'CS-FAC']);
        $this->assertTrue($overview['single_current_stock']);
        $this->assertContains(8, array_column($overview['data'], 'current_stock'));
        $this->assertSame(8, (int) DB::table('inventory_store_sync_tasks')
            ->where('id', $receipt['provider_sync_task']['id'])->value('target_quantity'));

        $websitePool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $websiteBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $websitePool, 'product_variant_id' => $variant->id,
            'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CurrentStockLedger::class)->setAbsolute($websiteBalance, 2, 'count:website', 'First Count');
        $transfers = app(FacilityTransferReview::class);
        $transferReview = $transfers->review($actor, $variant->id, $facility->id, null, 3, 'Rebalance');
        $transfers->confirm($actor, $transferReview['id'], 'current-transfer');
        $this->assertSame(5, app(CurrentStockLedger::class)->quantity($balance->id));
        $this->assertSame(5, app(CurrentStockLedger::class)->quantity($websiteBalance));
    }
}
