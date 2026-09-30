<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\UncertainStoreStockReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class AutomaticStoreStockReadbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_delayed_readback_confirms_exact_target_without_another_stock_write(): void
    {
        $c = $this->task();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 40, 'provider_location_id' => 'MYZ',
            'observed_at' => now()->toIso8601String(),
        ]);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);

        app(UncertainStoreStockReconciler::class)->run($c['task']);

        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $c['task'], 'status' => 'confirmed', 'readback_attempts' => 1,
        ]);
        $this->assertDatabaseHas('inventory_sync_observations', [
            'sync_task_id' => $c['task'], 'quantity' => 40,
        ]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance'], 'current_stock' => 40]);
    }

    public function test_mismatch_retries_are_bounded_and_leave_manual_recovery_available(): void
    {
        $c = $this->task();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->times(4)->andReturn([
            'observed_quantity' => 30, 'provider_location_id' => 'MYZ',
            'observed_at' => now()->toIso8601String(),
        ]);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            DB::table('inventory_store_sync_tasks')->where('id', $c['task'])
                ->update(['next_readback_at' => now()->subSecond()]);
            app(UncertainStoreStockReconciler::class)->run($c['task']);
        }
        app(UncertainStoreStockReconciler::class)->run($c['task']);

        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $c['task'], 'status' => 'uncertain', 'readback_attempts' => 4,
        ]);
        $this->assertNotNull(DB::table('inventory_store_sync_tasks')->where('id', $c['task'])
            ->value('readback_exhausted_at'));
        $this->assertDatabaseMissing('inventory_sync_observations', ['sync_task_id' => $c['task']]);
        $this->assertSame('confirmation_needs_review', app(\App\Services\Inventory\StoreManagement\StockSyncRecovery::class)
            ->details($c['store'], $c['variant'])['status']);
    }

    public function test_stale_balance_and_changed_mapping_cannot_confirm_an_old_task(): void
    {
        $c = $this->task();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('observe');
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        DB::table('inventory_balances')->where('id', $c['balance'])->increment('version');

        app(UncertainStoreStockReconciler::class)->run($c['task']);

        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task'], 'status' => 'superseded']);
        $this->assertDatabaseMissing('inventory_sync_observations', ['sync_task_id' => $c['task']]);
    }

    private function task(): array
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $variant = $fixture->sku()['variant'];
        $store = $fixture->store('shopee', 'readback');
        $account = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $account['id'])
            ->update(['current_stock' => 40, 'counted_at' => now()]);
        $balance = DB::table('inventory_balances')->find($account['id']);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100',
            'external_variant_id' => '200', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = (string) Str::uuid();
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $id, 'marketplace_store_id' => $store->id,
            'product_variant_id' => $variant->id, 'listing_variant_id' => $listing,
            'provider_external_product_id' => '100', 'provider_external_variant_id' => '200',
            'provider_location_id' => 'MYZ', 'stage' => 'transfer', 'status' => 'uncertain',
            'target_quantity' => 40, 'balance_version' => $balance->version,
            'event_key' => 'readback:'.$id, 'attempts' => 1,
            'next_readback_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['task' => $id, 'store' => (int) $store->id, 'variant' => (int) $variant->id,
            'balance' => (int) $balance->id];
    }
}
