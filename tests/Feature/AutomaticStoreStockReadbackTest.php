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

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_manual_confirmation_is_task_scoped_idempotent_and_never_continues_transfer_or_writes_stock(): void
    {
        $c = $this->manualTask();
        $waiting = (array) DB::table('inventory_store_sync_tasks')->find($c['task']);
        $waiting['id'] = (string) Str::uuid(); $waiting['event_key'] = 'waiting:'.$waiting['id']; $waiting['status'] = 'waiting_source';
        DB::table('inventory_store_sync_tasks')->insert($waiting);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn(['observed_quantity' => 40, 'provider_location_id' => 'MYZ']);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->actingAs($c['actor']);
        $this->postJson($c['url'], $c['payload'])->assertOk()->assertJsonPath('confirmation.status', 'Synced');
        $this->postJson($c['url'], $c['payload'])->assertOk()->assertJsonPath('confirmation.status', 'Synced');
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance'], 'current_stock' => 40]);
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
        $this->assertSame(1, DB::table('inventory_store_sync_attempts')->where('task_id', $c['task'])->where('source', 'manual_confirmation')->count());
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_manual_confirmation_rejects_permission_scope_version_and_destination_before_provider_read(): void
    {
        $c = $this->manualTask();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('observe'); $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->postJson($c['url'], $c['payload'])->assertForbidden();
        $this->actingAs($c['actor']);
        foreach (['store_id' => $c['store'] + 1, 'variant_id' => $c['variant'] + 1, 'balance_version' => 99,
            'provider_location_id' => 'OTHER', 'facility_id' => 999, 'provider_shop_id' => 'wrong-shop'] as $key => $value) {
            $this->postJson($c['url'], [...$c['payload'], $key => $value])->assertConflict();
        }
        $other = str_replace($c['operation'], (string) Str::uuid(), $c['url']);
        $this->postJson($other, $c['payload'])->assertNotFound();
        DB::table('inventory_balances')->where('id', $c['balance'])->increment('version');
        $this->postJson($c['url'], $c['payload'])->assertConflict();
    }

    public function test_manual_check_shares_auto_lock_and_records_unavailable_or_exhausted_without_stock_write(): void
    {
        $c = $this->manualTask(); $this->actingAs($c['actor']);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andThrow(new \RuntimeException('provider unavailable'));
        $publisher->shouldNotReceive('publish'); $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $lock = \Illuminate\Support\Facades\Cache::lock('stock-auto-readback:'.$c['store'], 90); $lock->get();
        $this->postJson($c['url'], $c['payload'])->assertConflict(); $lock->release();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task'])->update(['readback_attempts' => 4, 'readback_exhausted_at' => now()]);
        $this->postJson($c['url'], $c['payload'])->assertOk()->assertJsonPath('confirmation.status', 'Confirmation needs review');
        $receipt = json_decode(DB::table('inventory_store_sync_tasks')->where('id', $c['task'])->value('provider_receipt'), true);
        $this->assertSame('provider_readback_unavailable', $receipt['code']);
        $this->assertNotEmpty($receipt['readback_checked_at']);
        $this->postJson($c['url'], $c['payload'])->assertOk()->assertJsonPath('confirmation.check_count', 5);
        $this->postJson($c['url'], [...$c['payload'], 'operation_key' => (string) Str::uuid()])->assertStatus(429);
        $this->assertSame(1, DB::table('inventory_store_sync_attempts')->where('task_id', $c['task'])->where('source', 'manual_confirmation')->count());
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
    }

    private function manualTask(): array
    {
        $c = $this->task();
        $actor = \App\Models\User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $operation = (string) Str::uuid();
        $review = (string) Str::uuid();
        DB::table('inventory_store_reviews')->insert(['id' => $review, 'kind' => 'count', 'actor_id' => $actor->id,
            'configuration_version' => 1, 'balance_versions' => '{}', 'payload' => '{}', 'before' => '{}',
            'after' => '{}', 'blockers' => '[]', 'status' => 'confirmed', 'expires_at' => now(),
            'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_store_operations')->insert(['id' => $operation, 'review_id' => $review,
            'operation_key' => (string) Str::uuid(), 'kind' => 'count', 'actor_id' => $actor->id,
            'ledger_status' => 'applied', 'receipt' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $shop = (string) DB::table('marketplace_stores')->where('id', $c['store'])->value('external_shop_id');
        DB::table('inventory_store_sync_tasks')->where('id', $c['task'])->update(['operation_id' => $operation,
            'provider_receipt' => json_encode(['write_accepted' => true, 'provider_shop_id' => $shop, 'write_request_id' => 'write-1', 'message' => 'Old mismatch'])]);
        $version = (int) DB::table('inventory_balances')->where('id', $c['balance'])->value('version');
        return [...$c, 'actor' => $actor, 'operation' => $operation,
            'url' => '/admin/api/inventory/store-management/operations/'.$operation.'/tasks/'.$c['task'].'/confirmation-check',
            'payload' => ['operation_key' => (string) Str::uuid(), 'store_id' => $c['store'], 'variant_id' => $c['variant'],
                'balance_version' => $version, 'facility_id' => null, 'provider_location_id' => 'MYZ', 'provider_shop_id' => $shop]];
    }

    public function test_manual_check_revalidates_identity_after_readback_and_audits_without_confirming_stale_stock(): void
    {
        $c = $this->manualTask(); $this->actingAs($c['actor']);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturnUsing(function () use ($c): array {
            DB::table('inventory_balances')->where('id', $c['balance'])->increment('version');
            return ['observed_quantity' => 40, 'provider_location_id' => 'MYZ', 'access_token' => 'never-retain'];
        });
        $publisher->shouldNotReceive('publish'); $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($c['url'], $c['payload'])->assertOk()->assertJsonPath('confirmation.status', 'Stock changed');
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task'], 'status' => 'superseded']);
        $this->assertDatabaseMissing('inventory_sync_observations', ['sync_task_id' => $c['task']]);
        $receipt = DB::table('inventory_store_sync_tasks')->where('id', $c['task'])->value('provider_receipt');
        $this->assertStringNotContainsString('never-retain', $receipt);
    }

    public function test_changed_shop_or_model_cannot_be_checked_even_with_original_request(): void
    {
        $c = $this->manualTask(); $this->actingAs($c['actor']);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('observe'); $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        DB::table('marketplace_stores')->where('id', $c['store'])->update(['external_shop_id' => 'new-shop']);
        $this->postJson($c['url'], $c['payload'])->assertConflict();
        DB::table('marketplace_stores')->where('id', $c['store'])->update(['external_shop_id' => $c['payload']['provider_shop_id']]);
        DB::table('marketplace_listing_variants')->where('store_id', $c['store'])->update(['external_variant_id' => 'new-model']);
        $this->postJson($c['url'], $c['payload'])->assertConflict();
    }

    public function test_shared_warehouse_check_rejects_changed_takeover_and_projects_one_current_receipt(): void
    {
        $c = $this->manualTask(); $this->actingAs($c['actor']);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $balance = app(AccountResolver::class)->balance($c['variant'], 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['current_stock' => 40, 'counted_at' => now()]);
        $facility = DB::table('marketplace_store_facilities')->insertGetId([
            'marketplace_store_id' => $c['store'], 'provider_facility_id' => 'MYZ', 'name' => 'QA warehouse',
            'is_active' => true, 'shared_pool_takeover_enabled' => true, 'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task'])->update([
            'inventory_balance_id' => $balance['id'], 'balance_version' => $balance['version'], 'marketplace_store_facility_id' => $facility]);
        $payload = [...$c['payload'], 'facility_id' => $facility, 'balance_version' => $balance['version']];
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn(['observed_quantity' => 40, 'provider_location_id' => 'MYZ']);
        $publisher->shouldNotReceive('publish'); $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        DB::table('marketplace_store_facilities')->where('id', $facility)->update(['shared_pool_takeover_enabled' => false]);
        $this->postJson($c['url'], $payload)->assertConflict();
        DB::table('marketplace_store_facilities')->where('id', $facility)->update(['shared_pool_takeover_enabled' => true]);
        $this->postJson($c['url'], $payload)->assertOk()->assertJsonPath('confirmation.quantity', 40)->assertJsonPath('confirmation.error', null);
        $this->getJson('/admin/api/inventory/store-management/operations/'.$c['operation'])
            ->assertOk()->assertJsonPath('external_stages.0.confirmation.status', 'Synced');
        $this->getJson('/admin/api/inventory/store-management/overview')->assertOk()
            ->assertJsonPath('shared_store_confirmations.'.$c['variant'].'.'.$c['store'].'.0.quantity', 40)
            ->assertJsonPath('shared_store_confirmations.'.$c['variant'].'.'.$c['store'].'.0.error', null);
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
    }

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

    public function test_independent_legacy_available_stock_readback_keeps_its_existing_target_semantics(): void
    {
        $c = $this->task(); config(['inventory.current_stock_only' => false]);
        DB::table('inventory_balances')->where('id', $c['balance'])->update([
            'current_stock' => null, 'physical_stock' => 50, 'reserved_stock' => 7, 'safety_stock' => 3]);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn(['observed_quantity' => 40, 'provider_location_id' => 'MYZ']);
        $publisher->shouldNotReceive('publish'); $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        app(UncertainStoreStockReconciler::class)->run($c['task']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task'], 'status' => 'confirmed']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance'], 'physical_stock' => 50, 'current_stock' => null]);
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
            'id' => $id, 'marketplace_store_id' => $store->id, 'inventory_balance_id' => $account['id'],
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
