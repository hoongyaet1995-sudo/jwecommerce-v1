<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\RebaselineReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class InventoryRebaselineReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_topology_blocks_a_mapped_connected_store_without_mutation(): void
    {
        [$store, $variant] = $this->mappedStore();
        $before = DB::table('inventory_configuration')->where('id', 1)->first();

        $result = app(RebaselineReadiness::class)->inspect();

        $this->assertFalse($result['ready']);
        $this->assertContains('provider_topology_unverified', array_column($result['blockers'], 'code'));
        $this->assertSame((int) $before->version, $result['configuration_version']);
        $this->assertSame(0, DB::table('inventory_rebaseline_epochs')->count());
        $this->assertSame(0, DB::table('inventory_store_sync_tasks')->count());

        $url = '/admin/api/inventory/store-management/rebaseline/readiness';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))
            ->getJson($url)->assertOk()->assertJsonPath('ready', false);
    }

    public function test_open_reservations_pending_sync_and_counted_facility_are_separate_blockers(): void
    {
        [$store, $variant] = $this->mappedStore();
        app(FacilityRegistry::class)->confirm($store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'REBASE-WH-A', 'name' => 'A', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouse, 'kind' => 'facility',
            'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $pool, 'product_variant_id' => $variant->id,
            'physical_stock' => 10, 'reserved_stock' => 2, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => (string) Str::uuid(), 'marketplace_store_id' => $store->id,
            'product_variant_id' => $variant->id, 'stage' => 'stock', 'status' => 'queued',
            'balance_version' => 0, 'event_key' => 'rebaseline-pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(RebaselineReadiness::class)->inspect();
        $codes = array_column($result['blockers'], 'code');
        $this->assertContains('open_order_obligation', $codes);
        $this->assertContains('pending_sync', $codes);
        $this->assertContains('facility_owner_already_counted', $codes);
        $this->assertSame(1, $result['provider_destination_count']);
        $this->assertFalse($result['ready']);
    }

    public function test_unowned_reservation_blocks_and_disconnected_store_is_not_managed_destination(): void
    {
        [$store, $variant] = $this->mappedStore();
        DB::table('marketplace_stores')->where('id', $store->id)->update(['status' => 'disconnected']);
        DB::table('stock_allocations')->insert([
            'owner_type' => 'order', 'owner_id' => 8, 'line_key' => 'line-1',
            'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'reserved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(RebaselineReadiness::class)->inspect();
        $this->assertContains('open_order_obligation', array_column($result['blockers'], 'code'));
        $this->assertSame(0, $result['provider_destination_count']);
        $this->assertSame([], $result['stores']);
    }

    public function test_nonterminal_marketplace_order_blocks_even_before_local_reservation(): void
    {
        [$store] = $this->mappedStore();
        DB::table('marketplace_orders')->insert([
            'store_id' => $store->id, 'external_order_id' => 'PENDING-REBASE',
            'platform_status' => 'pending', 'platform_updated_at' => time(),
            'encrypted_payload' => 'test', 'fetched_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(RebaselineReadiness::class)->inspect();
        $this->assertContains('open_order_obligation', array_column($result['blockers'], 'code'));
        $this->assertGreaterThan(0, $result['open_order_obligation_count']);
    }

    private function mappedStore(): array
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'REBASE');
        $variant = $fixture->sku()['variant'];
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'REBASE-P',
            'external_variant_id' => 'REBASE-V', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$store, $variant];
    }
}
