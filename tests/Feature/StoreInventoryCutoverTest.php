<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreInventoryCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_writes_a_stable_manifest_without_mutating_business_records(): void
    {
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'LEGACY-WH', 'name' => 'Legacy Warehouse', 'status' => 'active', 'is_active' => true,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $products = DB::table('products')->count();
        $stores = DB::table('marketplace_stores')->count();
        $path = storage_path('framework/testing/store-inventory-cutover.json');
        @unlink($path);

        $this->artisan('inventory:store-cutover', ['--dry-run' => true, '--output' => $path])->assertSuccessful();

        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['schema_version']);
        $this->assertSame([$warehouse], array_column($manifest['legacy']['warehouses'], 'id'));
        $this->assertSame($manifest['hash'], hash('sha256', json_encode($manifest['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        $this->assertSame($products, DB::table('products')->count());
        $this->assertSame($stores, DB::table('marketplace_stores')->count());
        $this->assertSame('active', DB::table('inventory_warehouses')->where('id', $warehouse)->value('status'));
    }

    public function test_apply_rejects_changed_manifest_and_preserves_warehouse_state(): void
    {
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'LEGACY-WH', 'name' => 'Legacy Warehouse', 'status' => 'active', 'is_active' => true,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $path = storage_path('framework/testing/store-inventory-cutover-stale.json');
        @unlink($path);
        $this->artisan('inventory:store-cutover', ['--dry-run' => true, '--output' => $path])->assertSuccessful();
        DB::table('inventory_warehouses')->where('id', $warehouse)->increment('version');

        $this->artisan('inventory:store-cutover', ['--manifest' => $path, '--environment' => 'testing'])
            ->expectsOutputToContain('manifest no longer matches')
            ->assertFailed();

        $this->assertSame('active', DB::table('inventory_warehouses')->where('id', $warehouse)->value('status'));
    }

    public function test_apply_disables_only_empty_legacy_warehouse_writers_and_preserves_catalog_and_stores(): void
    {
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'LEGACY-WH', 'name' => 'Legacy Warehouse', 'status' => 'active', 'is_active' => true,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'website', 'warehouse_id' => $warehouse, 'is_enabled' => true, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $path = storage_path('framework/testing/store-inventory-cutover-apply.json');
        @unlink($path);
        $this->artisan('inventory:store-cutover', ['--dry-run' => true, '--output' => $path])->assertSuccessful();
        $products = DB::table('products')->count();
        $stores = DB::table('marketplace_stores')->count();

        $this->artisan('inventory:store-cutover', ['--manifest' => $path, '--environment' => 'testing'])->assertSuccessful();

        $this->assertSame('inactive', DB::table('inventory_warehouses')->where('id', $warehouse)->value('status'));
        $this->assertFalse((bool) DB::table('inventory_fulfillment_routes')->where('warehouse_id', $warehouse)->value('is_enabled'));
        $this->assertSame('independent_stores', DB::table('inventory_configuration')->where('id', 1)->value('operating_mode'));
        $this->assertSame($products, DB::table('products')->count());
        $this->assertSame($stores, DB::table('marketplace_stores')->count());
    }
}
