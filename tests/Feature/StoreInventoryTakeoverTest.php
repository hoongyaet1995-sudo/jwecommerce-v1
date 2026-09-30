<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function setupStore(string $status = 'connected'): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $f = new StoreInventoryFixture;
        $sku = $f->sku();
        $store = $f->store('lazada', 'TAKEOVER');
        DB::table('marketplace_stores')->where('id', $store->id)->update(['status' => $status]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => '1', 'external_variant_id' => '2', 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing, 'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        return compact('store', 'balance', 'admin');
    }

    public function test_takeover_and_pause_only_toggle_the_shared_pool_marker(): void
    {
        $c = $this->setupStore();
        DB::table('inventory_balances')->where('id', $c['balance']['id'])->update(['physical_stock' => 5, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now()]);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/takeover', [])
            ->assertOk()->assertJsonPath('shared_pool_takeover_enabled', true);
        $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id,
            'management_status' => 'setup_required', 'shared_pool_takeover_enabled' => true]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/pause', [])
            ->assertOk()->assertJsonPath('shared_pool_takeover_enabled', false);
        $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id,
            'management_status' => 'setup_required', 'shared_pool_takeover_enabled' => false]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance']['id'], 'physical_stock' => 5]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }

    public function test_pause_does_not_mutate_reservations(): void
    {
        $c = $this->setupStore();
        DB::table('inventory_balances')->where('id', $c['balance']['id'])->update(['physical_stock' => 5, 'reserved_stock' => 2, 'safety_stock' => 0, 'counted_at' => now()]);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/pause')->assertOk();
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance']['id'], 'reserved_stock' => 2]);
    }

    public function test_takeover_requires_shared_mode_connected_store_and_super_admin(): void
    {
        $c = $this->setupStore('disconnected');
        $url = '/admin/api/inventory/store-management/stores/'.$c['store']->id.'/takeover';
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        DB::table('marketplace_stores')->where('id', $c['store']->id)->update(['status' => 'connected']);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($url)->assertForbidden();
        $this->assertDatabaseMissing('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id]);
    }

    public function test_lazada_warehouse_takeover_only_changes_its_own_marker(): void
    {
        $c = $this->setupStore();
        $storeId = $c['store']->id;
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$storeId.'/takeover')->assertOk();
        DB::table('marketplace_store_facility_scans')->insert(['marketplace_store_id' => $storeId,
            'status' => 'verified', 'topology' => 'multi_warehouse', 'created_at' => now(), 'updated_at' => now()]);
        $first = DB::table('marketplace_store_facilities')->insertGetId(['marketplace_store_id' => $storeId,
            'provider_facility_id' => 'WH-1', 'name' => 'Warehouse 1', 'is_active' => true,
            'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $second = DB::table('marketplace_store_facilities')->insertGetId(['marketplace_store_id' => $storeId,
            'provider_facility_id' => 'WH-2', 'name' => 'Warehouse 2', 'is_active' => true,
            'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $url = '/admin/api/inventory/store-management/stores/'.$storeId.'/facilities/'.$first;

        $this->actingAs($c['admin'])->postJson($url.'/takeover')->assertOk()
            ->assertJsonPath('shared_pool_takeover_enabled', true);
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $first, 'shared_pool_takeover_enabled' => true]);
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $second, 'shared_pool_takeover_enabled' => false]);
        $this->getJson('/admin/api/inventory/store-management/stores/'.$storeId.'/facilities')->assertOk()
            ->assertJsonPath('facilities.0.shared_pool_takeover_enabled', true);
        $this->postJson($url.'/takeover')->assertOk();
        $this->postJson('/admin/api/inventory/store-management/stores/'.$storeId.'/pause')->assertOk();
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $first, 'shared_pool_takeover_enabled' => true]);
        $this->postJson($url.'/takeover')->assertUnprocessable();
        $this->postJson($url.'/pause')->assertOk()->assertJsonPath('shared_pool_takeover_enabled', false);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }

    public function test_warehouse_takeover_requires_shared_mode_verified_active_lazada_warehouse_and_super_admin(): void
    {
        $c = $this->setupStore();
        $storeId = $c['store']->id;
        $facility = DB::table('marketplace_store_facilities')->insertGetId(['marketplace_store_id' => $storeId,
            'provider_facility_id' => 'WH-1', 'name' => 'Warehouse 1', 'is_active' => true,
            'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $url = '/admin/api/inventory/store-management/stores/'.$storeId.'/facilities/'.$facility.'/takeover';
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        DB::table('marketplace_store_facility_scans')->insert(['marketplace_store_id' => $storeId,
            'status' => 'verified', 'topology' => 'single_warehouse', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson($url)->assertUnprocessable();
        DB::table('inventory_store_accounts')->where('marketplace_store_id', $storeId)
            ->update(['shared_pool_takeover_enabled' => true]);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($url)->assertForbidden();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        $this->assertDatabaseHas('marketplace_store_facilities', ['id' => $facility, 'shared_pool_takeover_enabled' => false]);
    }
}
