<?php

namespace Tests\Feature;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Jobs\ProcessStoreInventorySync;
use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\SyncOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

final class FakeStoreInventoryPublisher implements StoreInventoryPublisher
{
    public array $calls = [];

    public string $mode = 'confirmed';

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        $this->calls[] = compact('quantity', 'eventKey');
        if ($this->mode === 'uncertain') {
            throw new StoreInventoryResultUncertain('connection lost');
        }
        if ($this->mode === 'auth') {
            throw new StoreInventoryAuthorizationExpired('token expired');
        }

        return ['provider' => $store->platform, 'confirmed_quantity' => $quantity, 'request_id' => 'safe-123'];
    }
}

class StoreInventorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_job_is_bound_to_the_marketplace_worker_queue(): void
    {
        $job = new ProcessStoreInventorySync('task-1');

        $this->assertSame('marketplace', $job->connection);
        $this->assertSame('marketplace', $job->queue);
    }

    private function staged(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'SYNC-'.strtoupper(bin2hex(random_bytes(3))));
        $source = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        $destination = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        foreach ([[$source, 10], [$destination, 4]] as [$balance, $physical]) {
            DB::table('inventory_balances')->where('id', $balance['id'])->update([
                'physical_stock' => $physical, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
            ]);
        }
        DB::table('inventory_store_accounts')->where('account_key', 'marketplace:'.$store->id)->update(['management_status' => 'managed']);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listingId, 'store_id' => $store->id,
            'product_variant_id' => $sku['variant']->id, 'actor_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_tokens')->insert([
            'store_id' => $store->id, 'access_token' => 'placeholder', 'access_expires_at' => now()->addHour(),
            'application_id' => 'test', 'credential_source' => 'oauth', 'created_at' => now(), 'updated_at' => now(),
        ]);
        config([
            'marketplace.stock_sync_operations_enabled' => true,
            'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => true,
            'marketplace.stock_sync_canary_identities' => ["lazada:{$store->id}:{$listingId}"],
        ]);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, ['kind' => 'transfer', 'source_balance_id' => $source['id'],
            'destination_balance_id' => $destination['id'], 'quantity' => 2, 'reason' => 'Allocation'])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'sync-transfer-'.bin2hex(random_bytes(4))])->assertOk()->json();
        $task = DB::table('inventory_store_sync_tasks')->where('operation_id', $receipt['operation_id'])->where('stage', 'destination')->first();

        return compact('actor', 'source', 'destination', 'task', 'listingId', 'store', 'sku');
    }

    public function test_worker_supersedes_a_stale_balance_revision_without_publishing(): void
    {
        $c = $this->staged();
        DB::table('inventory_balances')->where('id', $c['destination']['id'])->update(['reserved_stock' => 2, 'version' => DB::raw('version + 1')]);
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'superseded']);
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
    }

    public function test_response_lost_is_uncertain_and_requires_reviewed_retry_while_auth_expiry_is_explicit(): void
    {
        $c = $this->staged();
        $fake = new FakeStoreInventoryPublisher;
        $fake->mode = 'uncertain';
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'uncertain']);
        $this->assertDatabaseHas('inventory_store_sync_attempts', ['task_id' => $c['task']->id, 'status' => 'processing', 'source' => 'worker']);
        $this->assertDatabaseHas('inventory_store_sync_attempts', ['task_id' => $c['task']->id, 'status' => 'uncertain', 'source' => 'worker']);
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(1, $fake->calls);
        $reconciler = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $reconciler->shouldReceive('observe')->once()->andReturn([
            'provider' => 'lazada', 'observed_quantity' => 3, 'observed_at' => now()->toIso8601String(),
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $reconciler);
        $before = DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->value('provider_receipt');
        Bus::fake();
        $this->actingAs($c['actor'])->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertConflict()->assertJsonPath('error.code', 'provider_quantity_differs');
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'superseded']);
        $this->assertSame($before, DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->value('provider_receipt'));
        $this->assertDatabaseHas('inventory_store_sync_attempts', ['task_id' => $c['task']->id, 'status' => 'reconciliation_mismatch', 'actor_id' => $c['actor']->id]);
        $this->assertSame(3, DB::table('inventory_store_sync_attempts')->where('task_id', $c['task']->id)->count());
        Bus::assertNothingDispatched();
    }

    public function test_successful_reconciliation_releases_waiting_and_queued_work(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'uncertain', 'provider_location_id' => 'ORIGINAL-WH']);
        $fresh = DB::table('inventory_balances')->find($c['destination']['id']);
        $later = app(SyncOutbox::class)->enqueueStore(null, $fresh, (int) $c['store']->id, 'account', 'after-reconcile');
        $reconciler = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $reconciler->shouldReceive('observe')->once()->withArgs(fn ($store, $listing): bool => $listing->id === $c['listingId']
            && $listing->sync_provider_location_id === 'ORIGINAL-WH')
            ->andReturn(['provider' => 'lazada', 'observed_quantity' => (int) $c['task']->target_quantity]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $reconciler);
        Bus::fake();

        $this->actingAs($c['actor'])->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertOk()->assertJsonPath('status', 'confirmed');

        Bus::assertDispatched(ProcessStoreInventorySync::class, fn (ProcessStoreInventorySync $job): bool => $job->taskId === $later['id']);
    }

    public function test_later_ledger_mutation_cannot_bypass_an_uncertain_store_sku_write(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'uncertain']);
        $fresh = DB::table('inventory_balances')->find($c['destination']['id']);
        $later = app(SyncOutbox::class)->enqueueStore(null, $fresh, (int) $c['store']->id, 'account', 'later-ledger-change');

        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($later['id']))->handle($fake);

        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $later['id'], 'status' => 'queued']);
    }

    public function test_attempt_evidence_prevents_sync_task_deletion(): void
    {
        $c = $this->staged();
        $fake = new FakeStoreInventoryPublisher;
        $fake->mode = 'uncertain';
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->delete();
    }

    public function test_reviewed_mutation_creates_exact_order_boundary_before_sync_task(): void
    {
        $c = $this->staged();

        $this->assertSame($c['listingId'], (int) $c['task']->listing_variant_id);
        $this->assertSame('100', $c['task']->provider_external_product_id);
        $this->assertSame('200', $c['task']->provider_external_variant_id);

        $this->assertDatabaseHas('inventory_order_activations', [
            'channel_key' => 'marketplace:'.$c['store']->id,
            'product_variant_id' => $c['sku']['variant']->id,
            'inventory_balance_id' => $c['destination']['id'],
        ]);
    }

    public function test_destination_waits_while_source_outcome_is_uncertain(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('operation_id', $c['task']->operation_id)->where('stage', 'source')->update(['status' => 'uncertain']);
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'waiting_source']);
    }

    public function test_mapping_or_canary_change_after_enqueue_supersedes_without_transport(): void
    {
        $mapping = $this->staged();
        DB::table('marketplace_variant_mappings')->where('listing_variant_id', $mapping['listingId'])->delete();
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($mapping['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $mapping['task']->id, 'status' => 'superseded']);

        $replacement = $this->staged();
        DB::table('marketplace_variant_mappings')->where('listing_variant_id', $replacement['listingId'])->delete();
        $newListing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $replacement['store']->id, 'external_product_id' => '101', 'external_variant_id' => '201',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $newListing, 'store_id' => $replacement['store']->id,
            'product_variant_id' => $replacement['sku']['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        config(['marketplace.stock_sync_canary_identities' => ["lazada:{$replacement['store']->id}:{$newListing}"]]);
        (new ProcessStoreInventorySync($replacement['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $replacement['task']->id, 'status' => 'superseded']);

        $canary = $this->staged();
        config(['marketplace.stock_sync_canary_identities' => []]);
        (new ProcessStoreInventorySync($canary['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $canary['task']->id, 'status' => 'superseded']);
    }

    public function test_processing_task_is_never_published_by_a_second_worker_and_requires_reconciliation(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'processing']);
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->actingAs($c['actor'])->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertConflict()->assertJsonPath('error.code', 'reconciliation_required');
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'uncertain']);
    }

    public function test_shared_balance_fans_out_to_every_managed_mapped_marketplace_store(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['physical_stock' => 7, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now()]);
        foreach (['lazada', 'shopee'] as $platform) {
            $store = $fixture->store($platform, strtoupper($platform));
            DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id, 'marketplace_store_id' => $store->id, 'management_status' => 'managed', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => 'p'.$store->id, 'external_variant_id' => 'v'.$store->id, 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing, 'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $fresh = DB::table('inventory_balances')->find($balance['id']);
        $stages = app(SyncOutbox::class)->enqueueMutation(null, $fresh, 'order', 'shared-test');
        $this->assertCount(2, $stages);
        $this->assertSame([6, 6], array_column($stages, 'target_quantity'));
        $this->assertDatabaseCount('inventory_store_sync_tasks', 2);
    }
}
