<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityOrderActivation;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_orders_activate_only_one_verified_counted_provider_facility(): void
    {
        config(['marketplace.multiwarehouse_orders_enabled' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ACTIVATE-FACILITY');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $facilityA = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->first();
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-ACTIVATE-A', 'name' => 'Activation A', 'is_active' => true,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facilityA->id)
            ->update(['inventory_warehouse_id' => $warehouseId]);
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balanceId = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(FacilityOrderActivation::class);
        $route = $service->activate($actor, (int) $store->id, (int) $facilityA->id,
            (int) $variant->id, 'Start new orders after verified count');
        $this->assertSame($balanceId, $route['inventory_balance_id']);
        $this->assertSame('WH-A', $route['provider_facility_id']);
        $this->assertSame($route['id'], $service->activate($actor, (int) $store->id, (int) $facilityA->id,
            (int) $variant->id, 'Repeated activation')['id']);
        $this->assertNull($service->eligible((int) $store->id, (int) $variant->id,
            'WH-B', now()->addMinute()));
        $this->assertSame($route['id'], $service->eligible((int) $store->id, (int) $variant->id,
            'WH-A', now()->addMinute())['id']);
    }
}
