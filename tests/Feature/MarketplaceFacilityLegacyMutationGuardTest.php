<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityLegacyMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_level_review_cannot_mutate_a_facility_balance_without_facility_identity(): void
    {
        $variant = (new StoreInventoryFixture)->sku()['variant'];
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-GUARD-01', 'name' => 'Facility guard', 'is_active' => true,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balanceId = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)->postJson('/admin/api/inventory/store-management/reviews', [
            'kind' => 'intake', 'balance_id' => $balanceId, 'quantity' => 5,
            'reason' => 'Inbound stock',
        ])->assertUnprocessable();

        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $balanceId)->value('physical_stock'));
        $this->assertDatabaseCount('inventory_store_reviews', 0);
    }

    public function test_legacy_store_balance_requires_explicit_verified_no_warehouse_topology_after_cutover(): void
    {
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'LEGACY-GATE');
        $variant = $fixture->sku()['variant'];
        $balance = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = '/admin/api/inventory/store-management/reviews';
        $payload = ['kind' => 'count', 'balance_id' => $balance['id'],
            'physical_count' => 7, 'reason' => 'Initial count'];

        $this->actingAs($admin)->postJson($url, $payload)->assertUnprocessable();
        app(FacilityRegistry::class)->confirm((int) $store->id, []);
        $this->postJson($url, $payload)->assertCreated();
        app(FacilityRegistry::class)->fail((int) $store->id, 'provider_unavailable');
        $this->postJson($url, $payload)->assertUnprocessable();
    }
}
