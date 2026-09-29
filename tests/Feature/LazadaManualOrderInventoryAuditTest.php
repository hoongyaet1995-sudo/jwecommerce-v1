<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\LazadaManualOrderInventoryAudit;
use App\Services\Marketplace\LazadaInventoryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LazadaManualOrderInventoryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_rehomes_one_existing_reservation_to_the_exact_facility_only_once(): void
    {
        $fixture = new StoreInventoryFixture;
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $store = $fixture->store('lazada', 'MANUAL-AUDIT');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-EXACT', 'name' => 'Exact']]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId(['code' => 'JW-MANUAL-AUDIT', 'name' => 'Exact', 'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)->update(['inventory_warehouse_id' => $warehouse]);
        $oldPool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'store:marketplace:'.$store->id, 'kind' => 'store', 'marketplace_store_id' => $store->id, 'created_at' => now(), 'updated_at' => now()]);
        $newPool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'facility:'.$warehouse, 'kind' => 'facility', 'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now()]);
        $oldBalance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $oldPool, 'product_variant_id' => $variant->id, 'physical_stock' => 20, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $newBalance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $newPool, 'product_variant_id' => $variant->id, 'physical_stock' => 3, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $legacyActivation = DB::table('inventory_order_activations')->insertGetId(['channel_key' => 'marketplace:'.$store->id, 'product_variant_id' => $variant->id, 'inventory_balance_id' => $oldBalance, 'actor_id' => $actor->id, 'reason' => 'Legacy stock count', 'orders_created_after' => now()->subDay(), 'created_at' => now()->subDay()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200', 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store->id, 'listing_variant_id' => $listing, 'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id, 'external_order_id' => '123456', 'platform_status' => 'pending', 'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $allocation = DB::table('stock_allocations')->insertGetId(['owner_type' => 'marketplace_order', 'owner_id' => $order, 'line_key' => '123457', 'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'reserved', 'inventory_order_activation_id' => $legacyActivation, 'inventory_balance_id' => $oldBalance, 'created_at' => now(), 'updated_at' => now()]);
        $line = ['line_key' => '123457', 'external_product_id' => '100', 'external_variant_id' => '200', 'warehouse_code' => 'WH-EXACT', 'target' => 'reserved', 'quantity' => 1];

        $audit = app(LazadaManualOrderInventoryAudit::class);
        $first = $audit->reconcile($order, [$line], $actor);
        $second = $audit->reconcile($order, [$line], $actor);

        $this->assertSame('corrected', $first[0]['result']);
        $this->assertSame('already_correct', $second[0]['result']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'physical_stock' => 20, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'physical_stock' => 3, 'reserved_stock' => 1]);
        $this->assertDatabaseHas('stock_allocations', ['id' => $allocation, 'inventory_balance_id' => $newBalance]);
        $this->assertDatabaseHas('marketplace_order_inventory_corrections', [
            'stock_allocation_id' => $allocation, 'from_balance_id' => $oldBalance,
            'to_balance_id' => $newBalance, 'actor_id' => $actor->id,
        ]);

        $nextOrder = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '123458', 'platform_status' => 'pending',
            'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $next = $line;
        $next['line_key'] = '123459';
        $this->assertSame('reserved', $audit->reconcile($nextOrder, [$next], $actor)[0]['result']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'reserved_stock' => 2]);

        $webhookOrder = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '123460', 'platform_status' => 'pending',
            'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $webhookLine = $line;
        $webhookLine['line_key'] = '123461';
        app(LazadaInventoryExecutor::class)->apply($webhookOrder, [$webhookLine], now()->timestamp, now()->toImmutable());
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'reserved_stock' => 3]);

        DB::table('inventory_balances')->where('id', $newBalance)->update(['physical_stock' => 5]);
        DB::table('inventory_balances')->where('id', $oldBalance)->update(['physical_stock' => 19]);
        $shippedOrder = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '123462', 'platform_status' => 'shipped',
            'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $shippedAllocation = DB::table('stock_allocations')->insertGetId([
            'owner_type' => 'marketplace_order', 'owner_id' => $shippedOrder, 'line_key' => '123463',
            'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'consumed',
            'inventory_order_activation_id' => $legacyActivation, 'inventory_balance_id' => $oldBalance,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_allocation_movements')->insert([
            'stock_allocation_id' => $shippedAllocation, 'inventory_balance_id' => $oldBalance,
            'event' => 'consumed', 'physical_delta' => -1, 'reserved_delta' => -1,
            'physical_after' => 19, 'reserved_after' => 0, 'balance_version' => 2,
            'created_at' => now(),
        ]);
        $shippedLine = $line;
        $shippedLine['line_key'] = '123463';
        $shippedLine['target'] = 'consumed';
        $this->assertSame('corrected', $audit->reconcile($shippedOrder, [$shippedLine], $actor)[0]['result']);
        $this->assertSame('already_correct', $audit->reconcile($shippedOrder, [$shippedLine], $actor)[0]['result']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'physical_stock' => 20, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'physical_stock' => 4, 'reserved_stock' => 3]);
        $this->assertDatabaseHas('stock_allocations', ['id' => $shippedAllocation, 'status' => 'consumed', 'inventory_balance_id' => $newBalance]);

        DB::table('inventory_balances')->where('id', $oldBalance)->update(['reserved_stock' => 1]);
        $twoLineOrder = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '123464', 'platform_status' => 'pending',
            'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $twoLineAllocation = DB::table('stock_allocations')->insertGetId([
            'owner_type' => 'marketplace_order', 'owner_id' => $twoLineOrder, 'line_key' => '123465',
            'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'reserved',
            'inventory_order_activation_id' => $legacyActivation, 'inventory_balance_id' => $oldBalance,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $validLine = $line;
        $validLine['line_key'] = '123465';
        $invalidLine = $line;
        $invalidLine['line_key'] = '123466';
        $invalidLine['warehouse_code'] = 'UNKNOWN-WH';
        try {
            $audit->reconcile($twoLineOrder, [$validLine, $invalidLine], $actor);
            $this->fail('Partial inventory correction was committed');
        } catch (\App\Services\Marketplace\MarketplaceInventoryException $exception) {
            $this->assertSame('exact_facility_count_required', $exception->getMessage());
        }
        $this->assertDatabaseHas('stock_allocations', ['id' => $twoLineAllocation, 'inventory_balance_id' => $oldBalance]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'reserved_stock' => 1]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'reserved_stock' => 3]);

        $cancelLine = $validLine;
        $cancelLine['target'] = 'released';
        $this->assertSame('released', $audit->reconcile($twoLineOrder, [$cancelLine], $actor)[0]['result']);
        $this->assertSame('already_correct', $audit->reconcile($twoLineOrder, [$cancelLine], $actor)[0]['result']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'reserved_stock' => 3]);

        $unbookedOrder = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '123467', 'platform_status' => 'pending',
            'platform_updated_at' => now()->timestamp, 'encrypted_payload' => 'test', 'fetched_at' => now()]);
        $unbooked = DB::table('stock_allocations')->insertGetId([
            'owner_type' => 'marketplace_order', 'owner_id' => $unbookedOrder, 'line_key' => '123468',
            'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'reserved',
            'inventory_order_activation_id' => $legacyActivation, 'inventory_balance_id' => $oldBalance,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unbookedLine = $line;
        $unbookedLine['line_key'] = '123468';
        $this->assertSame('corrected', $audit->reconcile($unbookedOrder, [$unbookedLine], $actor)[0]['result']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'reserved_stock' => 4]);
        $this->assertDatabaseHas('stock_allocations', ['id' => $unbooked, 'inventory_balance_id' => $newBalance]);

        $webhookLine['target'] = 'consumed';
        app(LazadaInventoryExecutor::class)->apply($webhookOrder, [$webhookLine], now()->timestamp + 1, now()->toImmutable());
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance,
            'physical_stock' => 3, 'reserved_stock' => 3]);
    }
}
