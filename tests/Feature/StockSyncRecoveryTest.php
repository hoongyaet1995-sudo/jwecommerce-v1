<?php

namespace Tests\Feature;

use App\Exceptions\StoreInventoryResultUncertain;
use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StockSyncRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        Bus::fake();
        config(['marketplace.stock_sync_operations_enabled' => true, 'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => false, 'inventory.sync_readback_delay_ms' => 0]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'recovery');
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['physical_stock' => 25, 'reserved_stock' => 2, 'safety_stock' => 1, 'counted_at' => now()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200', 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store->id, 'listing_variant_id' => $listing, 'product_variant_id' => $sku['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_tokens')->insert(['store_id' => $store->id, 'access_token' => 'test', 'access_expires_at' => now()->addHour(), 'application_id' => 'test', 'credential_source' => 'oauth', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));

        return '/admin/api/inventory/store-management/stores/'.$store->id.'/skus/'.$sku['variant']->id.'/sync';
    }

    public function test_details_unknown_and_check_only_reads_platform(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn(['observed_quantity' => 9, 'observed_at' => now()->toIso8601String()]);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->getJson($url)->assertOk()->assertJsonPath('marketplaceReportedStock', null)->assertJsonPath('jwAvailableStock', 22);
        $this->postJson($url.'/check')->assertOk()->assertJsonPath('marketplaceReportedStock', 9)->assertJsonPath('status', 'not_synced');
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }

    public function test_direct_resync_is_idempotent_and_does_not_enqueue_or_change_stock(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn ($s, $l, $q, $key) => $q === 22)->andReturn(['provider' => 'lazada', 'requested_quantity' => 22, 'observed_quantity' => 22, 'confirmation_method' => 'provider_readback', 'confirmed_at' => now()->toIso8601String()]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $key = 'a7362401-b46c-4079-bf24-4e036874f111';
        $this->postJson($url.'/resync', ['operation_key' => $key])->assertOk()->assertJsonPath('status', 'synced');
        $this->postJson($url.'/resync', ['operation_key' => $key])->assertOk()->assertJsonPath('status', 'synced');
        $this->assertDatabaseHas('inventory_balances', ['physical_stock' => 25, 'reserved_stock' => 2]);
        $this->assertDatabaseCount('inventory_store_operations', 1);
        $this->assertDatabaseHas('inventory_store_operations', ['kind' => 'platform_sync']);
        $this->getJson('/admin/api/inventory/store-management/history')->assertOk()
            ->assertJsonPath('data.0.kind', 'platform_sync')->assertJsonPath('data.0.result.ledger_changed', false);
        Bus::assertNothingDispatched();
    }

    public function test_uncertain_stays_blocked_until_readback_matches(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new StoreInventoryResultUncertain('private diagnostic'));
        $publisher->shouldReceive('observe')->times(2)->andReturn(['observed_quantity' => 9, 'observed_at' => now()->toIso8601String()]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($url.'/resync', ['operation_key' => 'a7362401-b46c-4079-bf24-4e036874f111'])->assertOk()->assertJsonPath('status', 'awaiting_confirmation')->assertDontSee('private diagnostic');
        $this->postJson($url.'/resync', ['operation_key' => 'b7362401-b46c-4079-bf24-4e036874f111'])->assertStatus(409);
    }

    public function test_readback_reconciles_uncertain_without_sending_again(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new StoreInventoryResultUncertain('timeout'));
        $publisher->shouldReceive('observe')->times(3)->andReturn(['observed_quantity' => 9], ['observed_quantity' => 9], ['observed_quantity' => 22]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertOk();
        $this->postJson($url.'/check')->assertOk()->assertJsonPath('status', 'synced')->assertJsonPath('canResync', true);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['status' => 'confirmed', 'attempts' => 1]);
        Bus::assertNothingDispatched();
    }

    public function test_source_readback_confirmation_directly_continues_waiting_transfer_destination(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new StoreInventoryResultUncertain('timeout'));
        $publisher->shouldReceive('observe')->times(3)->andReturn(
            ['observed_quantity' => 9],
            ['observed_quantity' => 9],
            ['observed_quantity' => 22],
        );
        $publisher->shouldReceive('publish')->once()->andReturn([
            'provider' => 'lazada', 'requested_quantity' => 22, 'observed_quantity' => 22,
            'confirmation_method' => 'provider_readback',
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);

        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertOk();
        $source = DB::table('inventory_store_sync_tasks')->first();
        DB::table('inventory_store_sync_tasks')->where('id', $source->id)->update(['stage' => 'source']);
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => (string) Str::uuid(), 'operation_id' => $source->operation_id,
            'marketplace_store_id' => $source->marketplace_store_id,
            'product_variant_id' => $source->product_variant_id,
            'listing_variant_id' => $source->listing_variant_id,
            'provider_external_product_id' => $source->provider_external_product_id,
            'provider_external_variant_id' => $source->provider_external_variant_id,
            'stage' => 'destination', 'status' => 'waiting_source', 'target_quantity' => 22,
            'balance_version' => $source->balance_version, 'event_key' => (string) Str::uuid(),
            'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson($url.'/check')->assertOk();
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['stage' => 'source', 'status' => 'confirmed']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['stage' => 'destination', 'status' => 'confirmed', 'attempts' => 1]);
        Bus::assertNothingDispatched();
    }

    public function test_bulk_status_check_reads_provider_without_publishing_stock(): void
    {
        $url = $this->fixture();
        preg_match('#/stores/(\d+)/skus/(\d+)/sync$#', $url, $matches);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 22,
            'observed_at' => now()->toIso8601String(),
        ]);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);

        $this->postJson('/admin/api/inventory/store-management/sync-status/bulk-check', [
            'items' => [['store_id' => (int) $matches[1], 'variant_id' => (int) $matches[2]]],
        ])->assertOk()
            ->assertJsonPath('summary.requested', 1)
            ->assertJsonPath('summary.checked', 1)
            ->assertJsonPath('summary.failed', 0)
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.marketplaceReportedStock', 22);

        Bus::assertNothingDispatched();
    }

    public function test_bulk_status_check_limits_each_readback_chunk_to_ten_pairs(): void
    {
        $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('observe');
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);

        $items = collect(range(1, 11))->map(fn (int $id): array => ['store_id' => 1, 'variant_id' => $id])->all();
        $this->postJson('/admin/api/inventory/store-management/sync-status/bulk-check', ['items' => $items])
            ->assertUnprocessable();
    }

    public function test_changed_stock_during_send_is_not_reported_as_current_confirmation(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturnUsing(function () {
            DB::table('inventory_balances')->update(['physical_stock' => 30, 'version' => DB::raw('version + 1')]);

            return ['observed_quantity' => 22, 'confirmation_method' => 'provider_readback'];
        });
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertOk()
            ->assertJsonPath('status', 'sync_replaced')->assertJsonPath('jwAvailableStock', 27);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['status' => 'superseded']);
    }

    public function test_permissions_and_client_quantity_cannot_bypass_server_stock(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid(), 'quantity' => 999])->assertStatus(422);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->getJson($url)->assertForbidden();
        $this->postJson($url.'/check')->assertForbidden();
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_unmapped_uncounted_and_expired_stores_cannot_send(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        DB::table('marketplace_store_tokens')->update(['credential_source' => 'official_test', 'test_lease_expires_at' => now()->subMinute()]);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'auth_expired')->assertJsonPath('canResync', false);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
        DB::table('inventory_balances')->update(['counted_at' => null]);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
        DB::table('marketplace_variant_mappings')->delete();
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'sku_not_mapped');
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
    }

    public function test_old_observations_are_not_reused_after_listing_identity_changes(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn(['observed_quantity' => 22]);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->postJson($url.'/check')->assertOk()->assertJsonPath('status', 'synced');
        DB::table('marketplace_listing_variants')->update(['external_variant_id' => 'another-model']);
        $this->getJson($url)->assertOk()->assertJsonPath('marketplaceReportedStock', null)->assertJsonPath('status', 'not_synced');
        $this->assertDatabaseCount('inventory_sync_observations', 1);
        Bus::assertNothingDispatched();
    }

    public function test_renewed_official_test_lease_is_not_treated_as_expired_seller_oauth(): void
    {
        $url = $this->fixture();
        DB::table('marketplace_store_tokens')->update(['credential_source' => 'official_test',
            'test_lease_expires_at' => now()->addHour(), 'access_expires_at' => now()->subDay()]);
        $this->getJson($url)->assertOk()->assertJsonPath('canResync', true);
    }

    public function test_paused_store_and_master_switch_block_direct_sends(): void
    {
        $url = $this->fixture();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        config(['marketplace.stock_sync_operations_enabled' => false]);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
        config(['marketplace.stock_sync_operations_enabled' => true]);
        DB::table('inventory_store_accounts')->update(['management_status' => 'paused']);
        $this->getJson($url)->assertOk()->assertJsonPath('canResync', false);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }

    public function test_inflight_transfer_cannot_be_replaced_by_direct_recovery(): void
    {
        $url = $this->fixture();
        $store = DB::table('marketplace_stores')->first();
        $variant = DB::table('product_variants')->first();
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => (string) Str::uuid(), 'marketplace_store_id' => $store->id,
            'product_variant_id' => $variant->id, 'stage' => 'destination', 'status' => 'waiting_source',
            'balance_version' => 0, 'event_key' => 'existing-transfer', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $this->getJson($url)->assertOk()->assertJsonPath('canResync', false);
        $this->postJson($url.'/resync', ['operation_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['status' => 'waiting_source', 'event_key' => 'existing-transfer']);
    }
}
