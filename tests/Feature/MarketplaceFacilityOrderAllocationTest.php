<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StockAllocationService;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Marketplace\LazadaInventoryExecutor;
use App\Services\Marketplace\MarketplaceInventoryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityOrderAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazada_executor_uses_exact_activated_facility_and_rejects_another_location(): void
    {
        config(['marketplace.multiwarehouse_orders_enabled' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'EXEC-FACILITIES');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-EXEC-A', 'name' => 'JW Exec A', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouse, 'kind' => 'facility', 'warehouse_id' => $warehouse,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $variant->id,
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_facility_order_activations')->insert([
            'marketplace_store_id' => $store->id, 'marketplace_store_facility_id' => $facility->id,
            'product_variant_id' => $variant->id, 'inventory_balance_id' => $balance,
            'actor_id' => $actor->id, 'reason' => 'Count verified',
            'orders_created_after' => now()->subDay(), 'created_at' => now()->subDay(),
        ]);
        $order = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $store->id, 'external_order_id' => 'FAC-ORDER',
            'platform_status' => 'ready_to_ship', 'platform_updated_at' => 100,
            'encrypted_payload' => 'test', 'fetched_at' => now(),
        ]);
        $line = ['line_key' => 'LINE-1', 'external_product_id' => '100',
            'external_variant_id' => '200', 'warehouse_code' => 'WH-B', 'target' => 'reserved'];
        $created = now()->toImmutable();
        try {
            app(LazadaInventoryExecutor::class)->apply($order, [$line], 100, $created);
            $this->fail('Wrong provider facility accepted');
        } catch (MarketplaceInventoryException $exception) {
            $this->assertSame('warehouse_route_mismatch', $exception->getMessage());
        }
        $this->assertDatabaseCount('stock_allocations', 0);
        $line['warehouse_code'] = 'WH-A';
        app(LazadaInventoryExecutor::class)->apply($order, [$line], 100, $created);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balance, 'reserved_stock' => 1]);
        $this->assertDatabaseHas('stock_allocations', ['owner_id' => $order,
            'inventory_facility_order_activation_id' => 1]);
        $line['target'] = 'consumed';
        app(LazadaInventoryExecutor::class)->apply($order, [$line], 101, $created);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balance,
            'physical_stock' => 9, 'reserved_stock' => 0]);
    }

    public function test_two_order_lines_reserve_independent_facilities_and_release_or_consume_once(): void
    {
        config(['marketplace.multiwarehouse_orders_enabled' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ORDER-FACILITIES');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $routes = [];
        foreach (['A', 'B'] as $suffix) {
            $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->first();
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-ORDER-'.$suffix, 'name' => 'JW Order '.$suffix,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('id', $facility->id)
                ->update(['inventory_warehouse_id' => $warehouseId]);
            $poolId = DB::table('inventory_pools')->insertGetId([
                'pool_key' => 'facility:'.$warehouseId, 'kind' => 'facility', 'warehouse_id' => $warehouseId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $balanceId = DB::table('inventory_balances')->insertGetId([
                'inventory_pool_id' => $poolId, 'product_variant_id' => $variant->id,
                'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
                'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $activationId = DB::table('inventory_facility_order_activations')->insertGetId([
                'marketplace_store_id' => $store->id, 'marketplace_store_facility_id' => $facility->id,
                'product_variant_id' => $variant->id, 'inventory_balance_id' => $balanceId,
                'actor_id' => $actor->id, 'reason' => 'Verified facility route',
                'orders_created_after' => now()->subDay(), 'created_at' => now()->subDay(),
            ]);
            $routes[$suffix] = ['activation' => $activationId, 'balance' => $balanceId];
        }
        $allocations = app(StockAllocationService::class);
        $a = $allocations->reserve('marketplace_order', 100, 'line-a', (int) $variant->id, 1,
            null, $routes['A']['activation']);
        $b = $allocations->reserve('marketplace_order', 100, 'line-b', (int) $variant->id, 2,
            null, $routes['B']['activation']);

        $this->assertSame($a, $allocations->reserve('marketplace_order', 100, 'line-a',
            (int) $variant->id, 1, null, $routes['A']['activation']));
        $this->assertSame(1, (int) DB::table('inventory_balances')->where('id', $routes['A']['balance'])->value('reserved_stock'));
        $this->assertSame(2, (int) DB::table('inventory_balances')->where('id', $routes['B']['balance'])->value('reserved_stock'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $store->id, 'provider_location_id' => 'WH-A',
            'inventory_balance_id' => $routes['A']['balance'], 'target_quantity' => 9,
        ]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $store->id, 'provider_location_id' => 'WH-B',
            'inventory_balance_id' => $routes['B']['balance'], 'target_quantity' => 8,
        ]);
        $allocations->consume($a);
        $allocations->release($b);
        $allocations->consume($a);
        $this->assertSame(9, (int) DB::table('inventory_balances')->where('id', $routes['A']['balance'])->value('physical_stock'));
        $this->assertSame(0, (int) DB::table('inventory_balances')->where('id', $routes['A']['balance'])->value('reserved_stock'));
        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $routes['B']['balance'])->value('physical_stock'));
        $this->assertSame(0, (int) DB::table('inventory_balances')->where('id', $routes['B']['balance'])->value('reserved_stock'));
    }
}
