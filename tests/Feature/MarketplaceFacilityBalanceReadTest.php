<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityBalanceReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_stock_is_unknown_until_linked_to_a_reviewed_physical_owner(): void
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'FACILITY-BALANCE');
        $variant = $fixture->sku()['variant'];
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-FACILITY-A', 'name' => 'JW Facility A', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->update(['inventory_warehouse_id' => $warehouseId]);
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
            'physical_stock' => 8, 'reserved_stock' => 2, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $url = '/admin/api/inventory/store-management/stores/'.$store->id.'/skus/'.$variant->id.'/facilities';
        $this->getJson($url)->assertUnauthorized();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('topology', 'multi_warehouse')
            ->assertJsonPath('stock_operations_ready', false)
            ->assertJsonPath('facilities.0.provider_id', 'WH-A')
            ->assertJsonPath('facilities.0.physical', 8)
            ->assertJsonPath('facilities.0.reserved', 2)
            ->assertJsonPath('facilities.0.available', 6)
            ->assertJsonPath('facilities.1.provider_id', 'WH-B')
            ->assertJsonPath('facilities.1.physical', null)
            ->assertJsonPath('totals.known_physical', 8)
            ->assertJsonPath('totals.unknown_facilities', 1);

        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $overview = $this->actingAs($admin)->getJson('/admin/api/inventory/store-management/overview')
            ->assertOk()->json();
        $facilityRows = collect($overview['data'])->where('variant_id', $variant->id)
            ->where('pool_kind', 'facility')->values();
        $this->assertCount(1, $facilityRows);
        $this->assertSame(8, $facilityRows[0]['physical']);
        $this->assertSame('WH-A', $facilityRows[0]['provider_facility_id']);
    }
}
