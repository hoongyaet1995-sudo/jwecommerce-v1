<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\LegacyWarehouseStockNormalizer;
use App\Services\Inventory\StoreManagement\OverviewReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LegacyWarehouseStockNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_counted_legacy_warehouse_stock_moves_to_the_website_account_once_and_closes_readiness(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $sku = (new StoreInventoryFixture)->sku();
        DB::table('products')->where('id', $sku['product']->id)->update(['status' => 'active']);
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'LEGACY-NORMALIZE', 'name' => 'Legacy Normalize', 'status' => 'inactive', 'is_active' => false,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$warehouse, 'kind' => 'warehouse', 'warehouse_id' => $warehouse,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $legacyBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $sku['variant']->id,
            'physical_stock' => 22, 'reserved_stock' => null, 'safety_stock' => 2,
            'counted_at' => now(), 'version' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $first = app(LegacyWarehouseStockNormalizer::class)->normalize();
        $second = app(LegacyWarehouseStockNormalizer::class)->normalize();

        $this->assertSame(1, $first['moved']);
        $this->assertSame(22, $first['physical_units']);
        $this->assertSame(0, $first['conflicts']);
        $this->assertSame(0, $second['moved']);
        $this->assertDatabaseHas('inventory_balances', [
            'id' => $legacyBalance, 'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0,
        ]);
        $website = DB::table('inventory_balances as b')
            ->join('inventory_pools as p', 'p.id', '=', 'b.inventory_pool_id')
            ->where('p.pool_key', 'store:website')->where('b.product_variant_id', $sku['variant']->id)
            ->first(['b.physical_stock', 'b.reserved_stock', 'b.safety_stock', 'b.counted_at']);
        $this->assertNotNull($website);
        $this->assertSame(22, (int) $website->physical_stock);
        $this->assertSame(0, (int) $website->reserved_stock);
        $this->assertSame(2, (int) $website->safety_stock);
        $this->assertNotNull($website->counted_at);

        $overview = app(OverviewReader::class)->read();
        $this->assertSame(0, $overview['mode_catalog']['summary']['blocked']);
        $this->assertSame('ready_to_allocate', $overview['mode_catalog']['items'][0]['classification']);
        $this->assertSame(22, $overview['totals']['known']['physical']);
        $this->assertSame(20, $overview['totals']['known']['available']);
    }

    public function test_existing_nonzero_website_balance_blocks_automatic_merge_without_mutating_either_ledger(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $sku = (new StoreInventoryFixture)->sku();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'LEGACY-CONFLICT', 'name' => 'Legacy Conflict', 'status' => 'inactive', 'is_active' => false,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $legacyPool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$warehouse, 'kind' => 'warehouse', 'warehouse_id' => $warehouse,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $legacyPool, 'product_variant_id' => $sku['variant']->id,
            'physical_stock' => 7, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $websitePool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store', 'warehouse_id' => null, 'marketplace_store_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $websitePool, 'product_variant_id' => $sku['variant']->id,
            'physical_stock' => 3, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(LegacyWarehouseStockNormalizer::class)->normalize();

        $this->assertSame(0, $result['moved']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('inventory_pool_id', $legacyPool)->value('physical_stock'));
        $this->assertSame(3, (int) DB::table('inventory_balances')->where('inventory_pool_id', $websitePool)->value('physical_stock'));
    }
}
