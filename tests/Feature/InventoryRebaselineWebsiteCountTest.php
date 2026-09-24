<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\ReviewService;
use App\Services\Inventory\StoreManagement\OverviewReader;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class InventoryRebaselineWebsiteCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_first_count_writes_only_new_epoch_facility_and_does_not_publish_during_freeze(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $variantId = (new StoreInventoryFixture)->sku()['variant']->id;
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'EPOCH-WEB', 'name' => 'JW Storefront', 'status' => 'active', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'website', 'warehouse_id' => $warehouseId, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $oldPool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'shared', 'kind' => 'shared', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $oldBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $oldPool, 'product_variant_id' => $variantId,
            'physical_stock' => 20, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $epochId = (string) Str::uuid();
        DB::table('inventory_rebaseline_epochs')->insert([
            'id' => $epochId, 'status' => 'counting', 'operation_key' => 'website-epoch',
            'configuration_version' => 1, 'actor_id' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_configuration')->where('id', 1)->update(['active_rebaseline_epoch_id' => $epochId]);
        $newPool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $newBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $newPool, 'product_variant_id' => $variantId,
            'rebaseline_epoch_id' => $epochId, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $review = app(ReviewService::class)->create($admin, 'count', [
            'balance_id' => $newBalance, 'channel_key' => 'website', 'physical_count' => 8,
            'reason' => 'Storefront physical count',
        ]);
        $receipt = app(ReviewService::class)->confirm($admin, $review['id'], 'website-first-count');

        $this->assertSame('applied', $receipt['ledger_status']);
        $this->assertSame([], $receipt['external_stages']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'physical_stock' => 20]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'physical_stock' => 8]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
        $overview = app(OverviewReader::class)->read(['sku' => DB::table('product_variants')->where('id', $variantId)->value('sku')]);
        $this->assertSame(8, $overview['totals']['known']['physical']);
        $this->assertSame([$newBalance], array_column($overview['data'], 'balance_id'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReviewService::class)->create($admin, 'count', [
            'balance_id' => $newBalance, 'channel_key' => 'website', 'physical_count' => 99,
            'reason' => 'Second count is not allowed',
        ]);
    }

    public function test_verified_no_warehouse_store_counts_its_explicit_jw_physical_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'NO-WAREHOUSE');
        $variantId = $fixture->sku()['variant']->id;
        app(FacilityRegistry::class)->confirm((int) $store->id, []);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'EPOCH-NO-WH', 'name' => 'No provider warehouse physical site',
            'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $channel = 'marketplace:'.$store->id;
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => $channel, 'warehouse_id' => $warehouseId, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'NO-WH-PRODUCT',
            'external_variant_id' => 'NO-WH-VARIANT', 'observed_data' => '{}', 'fetched_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $epochId = (string) Str::uuid();
        DB::table('inventory_rebaseline_epochs')->insert([
            'id' => $epochId, 'status' => 'counting', 'operation_key' => 'no-warehouse-epoch',
            'configuration_version' => 1, 'actor_id' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_configuration')->where('id', 1)->update(['active_rebaseline_epoch_id' => $epochId]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $variantId,
            'rebaseline_epoch_id' => $epochId, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $review = app(ReviewService::class)->create($admin, 'count', [
            'balance_id' => $balance, 'channel_key' => $channel, 'physical_count' => 6,
            'reason' => 'Verified no-warehouse physical count',
        ]);
        $receipt = app(ReviewService::class)->confirm($admin, $review['id'], 'no-warehouse-count');

        $this->assertSame([], $receipt['external_stages']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balance, 'physical_stock' => 6]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }
}
