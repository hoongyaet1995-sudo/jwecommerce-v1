<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\FacilityMigrationPreview;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityMigrationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_explicit_complete_physical_allocation_and_never_changes_legacy_stock(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'MIGRATE');
        $variant = $fixture->sku()['variant'];
        $legacy = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $legacy['id'])->update([
            'physical_stock' => 25, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 3,
        ]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        foreach (['A', 'B'] as $suffix) {
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-'.$suffix, 'name' => 'JW '.$suffix, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)
                ->update(['inventory_warehouse_id' => $warehouseId]);
        }

        $planner = app(FacilityMigrationPreview::class);
        $unmapped = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertFalse($unmapped['ready']);
        $this->assertContains('sku_unmapped', $unmapped['blockers']);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mismatch = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 10]);
        $this->assertFalse($mismatch['ready']);
        $this->assertContains('physical_total_mismatch', $mismatch['blockers']);

        $matched = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertTrue($matched['ready']);
        $this->assertSame(25, $matched['source']['physical']);
        $this->assertSame(3, $matched['source']['version']);
        $this->assertSame(25, $matched['allocated_physical']);
        $this->assertSame(0, $matched['reserved']);
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $legacy['id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_pools')->where('kind', 'facility')->count());

        $firstWarehouseId = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->value('inventory_warehouse_id');
        $legacyWarehousePool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$firstWarehouseId, 'kind' => 'warehouse',
            'warehouse_id' => $firstWarehouseId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $poolConflict = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertFalse($poolConflict['ready']);
        $this->assertContains('physical_owner_conflict', $poolConflict['blockers']);
        DB::table('inventory_pools')->where('id', $legacyWarehousePool)->delete();

        $url = '/admin/api/inventory/store-management/stores/'.$store->id.'/skus/'.$variant->id.'/facilities/migration-preview';
        $this->postJson($url, ['physical_by_provider_id' => ['WH-A' => 10, 'WH-B' => 15]])->assertUnauthorized();
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($url, ['physical_by_provider_id' => ['WH-A' => 10, 'WH-B' => 15]])->assertForbidden();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($super)->postJson($url, ['physical_by_provider_id' => ['WH-A' => 10, 'WH-B' => 15]])
            ->assertOk()->assertJsonPath('ready', true)->assertJsonPath('source.physical', 25);

        app(FacilityRegistry::class)->fail((int) $store->id, 'provider_unavailable');
        $pending = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertFalse($pending['ready']);
        $this->assertContains('facility_topology_unverified', $pending['blockers']);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);

        DB::table('inventory_balances')->where('id', $legacy['id'])->update(['safety_stock' => 1]);
        $safety = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertFalse($safety['ready']);
        $this->assertContains('safety_stock_unassigned', $safety['blockers']);

        DB::table('inventory_balances')->where('id', $legacy['id'])->update(['safety_stock' => 0]);
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => (string) Str::uuid(), 'marketplace_store_id' => $store->id,
            'product_variant_id' => $variant->id, 'stage' => 'stock', 'status' => 'queued',
            'balance_version' => 3, 'event_key' => 'pending-facility-migration-sync',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pendingSync = $planner->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10, 'WH-B' => 15]);
        $this->assertFalse($pendingSync['ready']);
        $this->assertContains('pending_provider_sync', $pendingSync['blockers']);
    }

    public function test_preview_blocks_unassigned_order_reservations_even_when_totals_match(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ORDERS');
        $variant = $fixture->sku()['variant'];
        $legacy = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $legacy['id'])->update([
            'physical_stock' => 10, 'reserved_stock' => 2, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1,
        ]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-ORDER-A', 'name' => 'JW Order A', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->update(['inventory_warehouse_id' => $warehouseId]);
        DB::table('inventory_store_order_lines')->insert([
            'line_key' => 'ORDER-1', 'channel_key' => 'marketplace:'.$store->id,
            'product_variant_id' => $variant->id, 'inventory_balance_id' => $legacy['id'],
            'ordered_quantity' => 2, 'reserved_quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $preview = app(FacilityMigrationPreview::class)->preview((int) $store->id, (int) $variant->id, ['WH-A' => 10]);

        $this->assertFalse($preview['ready']);
        $this->assertContains('order_reservations_unassigned', $preview['blockers']);
        $this->assertSame(2, $preview['reserved']);
        $this->assertSame(2, (int) DB::table('inventory_balances')->where('id', $legacy['id'])->value('reserved_stock'));
    }
}
