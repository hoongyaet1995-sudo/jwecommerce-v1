<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreInventoryLegacyModeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_warehouse_mode_becomes_an_empty_shared_pool_without_copying_stock(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update([
            'operating_mode' => 'independent_warehouses',
            'version' => 12,
        ]);
        $legacyPool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'legacy:test', 'kind' => 'warehouse', 'warehouse_id' => null,
            'marketplace_store_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_20_000003_normalize_legacy_inventory_mode.php');
        $migration->up();

        $this->assertSame('shared_pool', DB::table('inventory_configuration')->where('id', 1)->value('operating_mode'));
        $this->assertSame(13, (int) DB::table('inventory_configuration')->where('id', 1)->value('version'));
        $this->assertTrue(DB::table('inventory_pools')->where('pool_key', 'shared')->exists());
        $this->assertTrue(DB::table('inventory_pools')->where('id', $legacyPool)->exists());
        $this->assertSame(0, DB::table('inventory_balances')->where('inventory_pool_id', $legacyPool)->count());
    }
}
