<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\FacilityOrderRouteResolver;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityOrderRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_provider_facility_resolves_one_counted_jw_balance_without_legacy_fallback(): void
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ORDER-ROUTE');
        $variant = $fixture->sku()['variant'];
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-ORDER-ROUTE', 'name' => 'JW Order Route', 'is_active' => true,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->update(['inventory_warehouse_id' => $warehouseId]);
        $poolId = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balanceId = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
            'physical_stock' => 9, 'reserved_stock' => 1, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)
            ->update(['ledger_migration_status' => 'complete']);

        $resolver = app(FacilityOrderRouteResolver::class);
        DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)
            ->update(['ledger_migration_status' => 'pending']);
        $this->assertSame('facility_ledger_not_ready', $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-A')['status']);
        DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)
            ->update(['ledger_migration_status' => 'complete']);
        $matched = $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-A');
        $this->assertSame('matched', $matched['status']);
        $this->assertSame($balanceId, $matched['balance_id']);
        $this->assertSame(8, $matched['available']);
        $this->assertSame('warehouse_unmatched', $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-UNKNOWN')['status']);
        $this->assertSame('physical_stock_unknown', $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-B')['status']);
        DB::table('marketplace_variant_mappings')->where('store_id', $store->id)->delete();
        $this->assertSame('sku_unmapped', $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-A')['status']);
        DB::table('marketplace_store_facility_scans')->where('marketplace_store_id', $store->id)
            ->update(['status' => 'verification_pending']);
        $this->assertSame('warehouse_verification_pending', $resolver->resolve((int) $store->id, (int) $variant->id, 'WH-A')['status']);
        $this->assertDatabaseCount('stock_allocations', 0);
    }
}
