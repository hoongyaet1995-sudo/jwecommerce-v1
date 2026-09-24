<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_binding_enabled' => true]);
    }

    public function test_super_admin_reviews_and_confirms_one_facility_binding_without_touching_stock(): void
    {
        [$storeId, $facilityId, $warehouseId] = $this->context();
        $reviewUrl = "/admin/api/inventory/store-management/stores/$storeId/facilities/$facilityId/binding-reviews";
        $this->postJson($reviewUrl, ['warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified'])->assertUnauthorized();
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($reviewUrl, ['warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified'])->assertForbidden();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $review = $this->actingAs($super)->postJson($reviewUrl, [
            'warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified',
        ])->assertCreated()->assertJsonPath('before_warehouse_id', null)
            ->assertJsonPath('after_warehouse_id', $warehouseId)->json();
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $facilityId, 'inventory_warehouse_id' => null]);

        $confirmUrl = '/admin/api/inventory/store-management/facility-binding-reviews/'.$review['id'].'/confirm';
        $first = $this->postJson($confirmUrl, ['operation_key' => 'bind-facility-1'])->assertOk()->json();
        $second = $this->postJson($confirmUrl, ['operation_key' => 'bind-facility-1'])->assertOk()->json();
        $this->assertSame($first, $second);
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $facilityId, 'inventory_warehouse_id' => $warehouseId]);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
        $this->assertDatabaseHas('marketplace_store_facility_scans', ['marketplace_store_id' => $storeId, 'ledger_migration_status' => 'pending']);
    }

    public function test_changed_provider_topology_rejects_stale_binding_review(): void
    {
        [$storeId, $facilityId, $warehouseId] = $this->context();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $review = $this->actingAs($super)->postJson(
            "/admin/api/inventory/store-management/stores/$storeId/facilities/$facilityId/binding-reviews",
            ['warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified'],
        )->assertCreated()->json();
        app(FacilityRegistry::class)->confirm($storeId, [
            ['provider_id' => 'WH-A', 'name' => 'Renamed A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);

        $this->postJson('/admin/api/inventory/store-management/facility-binding-reviews/'.$review['id'].'/confirm',
            ['operation_key' => 'stale-binding'])->assertConflict()->assertJsonPath('error.code', 'facility_topology_changed');
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $facilityId, 'inventory_warehouse_id' => null]);
    }

    public function test_pending_scan_and_duplicate_physical_target_block_binding(): void
    {
        [$storeId, $facilityId, $warehouseId] = $this->context();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $reviewUrl = "/admin/api/inventory/store-management/stores/$storeId/facilities/$facilityId/binding-reviews";
        app(FacilityRegistry::class)->fail($storeId, 'provider_unavailable');
        $this->actingAs($super)->postJson($reviewUrl, [
            'warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified',
        ])->assertUnprocessable();
        app(FacilityRegistry::class)->confirm($storeId, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $storeId)
            ->where('provider_facility_id', 'WH-B')->update(['inventory_warehouse_id' => $warehouseId]);
        $this->postJson($reviewUrl, [
            'warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified',
        ])->assertUnprocessable();
    }

    public function test_inactive_warehouse_status_and_disabled_binding_gate_block_review(): void
    {
        [$storeId, $facilityId, $warehouseId] = $this->context();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = "/admin/api/inventory/store-management/stores/$storeId/facilities/$facilityId/binding-reviews";
        DB::table('inventory_warehouses')->where('id', $warehouseId)->update(['status' => 'inactive']);
        $this->actingAs($super)->postJson($url, [
            'warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified',
        ])->assertUnprocessable();
        config(['marketplace.multiwarehouse_binding_enabled' => false]);
        $this->postJson($url, [
            'warehouse_id' => $warehouseId, 'reason' => 'Physical facility verified',
        ])->assertNotFound();
    }

    public function test_counted_physical_owner_cannot_be_silently_rebound(): void
    {
        [$storeId, $facilityId, $warehouseId] = $this->context();
        DB::table('marketplace_store_facilities')->where('id', $facilityId)
            ->update(['inventory_warehouse_id' => $warehouseId]);
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variant = (new StoreInventoryFixture)->sku()['variant'];
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
            'physical_stock' => 7, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-BIND-B', 'name' => 'JW Physical B', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($super)->postJson(
            "/admin/api/inventory/store-management/stores/$storeId/facilities/$facilityId/binding-reviews",
            ['warehouse_id' => $other, 'reason' => 'Change physical site'],
        )->assertUnprocessable();
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $facilityId, 'inventory_warehouse_id' => $warehouseId]);
        $this->assertDatabaseHas('inventory_balances', ['inventory_pool_id' => $poolId, 'physical_stock' => 7]);
    }

    /** @return array{int,int,int} */
    private function context(): array
    {
        $store = (new StoreInventoryFixture)->store('lazada', 'BIND');
        $scan = app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-BIND-A', 'name' => 'JW Physical A', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [(int) $store->id, (int) $scan['facilities'][0]['id'], $warehouseId];
    }
}
