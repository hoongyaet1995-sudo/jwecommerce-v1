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
use Illuminate\Database\QueryException;
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

    public mixed $observedAt = null;

    public function publish(object $store, object $listing, int $quantity, string $eventKey): array
    {
        $this->calls[] = compact('quantity', 'eventKey');
        if ($this->mode === 'uncertain') {
            throw new StoreInventoryResultUncertain('connection lost');
        }
        if ($this->mode === 'auth') {
            throw new StoreInventoryAuthorizationExpired('token expired');
        }

        return array_filter(['provider' => $store->platform, 'confirmed_quantity' => $quantity,
            'observed_quantity' => $quantity, 'observed_at' => $this->observedAt,
            'request_id' => 'safe-123'], fn ($value): bool => $value !== null);
    }
}

class StoreInventorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_job_is_bound_to_the_marketplace_worker_queue(): void
    {
        $job = new ProcessStoreInventorySync('task-1');

        $this->assertSame('marketplace', $job->connection);
        $this->assertSame('marketplace-stock', $job->queue);
    }

    private function staged(bool $direct = false, ?FakeStoreInventoryPublisher $publisher = null): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'SYNC-'.strtoupper(bin2hex(random_bytes(3))));
        app(\App\Services\Inventory\StoreManagement\FacilityRegistry::class)->confirm((int) $store->id, []);
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
            'marketplace.stock_sync_direct_enabled' => $direct,
        ]);
        if ($publisher) {
            $this->app->instance(StoreInventoryPublisher::class, $publisher);
        }
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, ['kind' => 'transfer', 'source_balance_id' => $source['id'],
            'destination_balance_id' => $destination['id'], 'quantity' => 2, 'reason' => 'Allocation'])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'sync-transfer-'.bin2hex(random_bytes(4))])->assertOk()->json();
        $task = DB::table('inventory_store_sync_tasks')->where('operation_id', $receipt['operation_id'])->where('stage', 'destination')->first();

        return compact('actor', 'source', 'destination', 'task', 'listingId', 'store', 'sku');
    }

    public function test_reviewed_transfer_calls_the_provider_inline_for_a_lazada_destination(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;

        $c = $this->staged(true, $publisher);

        $this->assertCount(1, $publisher->calls);
        $this->assertSame(6, $publisher->calls[0]['quantity']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $c['task']->id,
            'stage' => 'destination',
            'status' => 'confirmed',
        ]);
        Bus::assertNothingDispatched();
    }

    public function test_uncertain_direct_result_is_not_blindly_queued_for_a_second_write(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;
        $publisher->mode = 'uncertain';

        $c = $this->staged(true, $publisher);

        $this->assertCount(1, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'uncertain']);
        Bus::assertNotDispatched(\App\Jobs\ProcessStoreInventorySync::class);
    }

    public function test_iso_provider_timestamp_is_normalized_for_the_observation_read_model(): void
    {
        $publisher = new FakeStoreInventoryPublisher;
        $publisher->observedAt = '2026-09-22T09:52:11+00:00';

        $c = $this->staged(true, $publisher);

        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed']);
        $this->assertSame('2026-09-22 09:52:11', DB::table('inventory_sync_observations')
            ->where('sync_task_id', $c['task']->id)->value('observed_at'));
    }

    public function test_local_confirmation_persistence_error_is_uncertain_instead_of_retryable_failure(): void
    {
        $publisher = new FakeStoreInventoryPublisher;
        $publisher->observedAt = 'not-a-provider-timestamp';

        $c = $this->staged(true, $publisher);

        $this->assertCount(1, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'uncertain']);
        $receipt = json_decode((string) DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->value('provider_receipt'), true);
        $this->assertSame('local_confirmation_persistence_failed', $receipt['code']);
        $this->assertStringNotContainsString('SQLSTATE', $receipt['message']);
    }

    public function test_reviewed_failed_stage_retry_runs_directly_before_queue_fallback(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;
        $c = $this->staged(false, $publisher);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'failed']);

        $this->actingAs($c['actor'])
            ->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertCount(1, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed']);
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
        // Reproduce a previously queued task whose predecessor still needs readback.
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'uncertain']);
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

    public function test_later_ledger_mutation_sends_latest_stock_after_an_uncertain_store_sku_write(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'uncertain']);
        $fresh = DB::table('inventory_balances')->find($c['destination']['id']);
        $later = app(SyncOutbox::class)->enqueueStore(null, $fresh, (int) $c['store']->id, 'account', 'later-ledger-change');

        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($later['id']))->handle($fake);

        $this->assertCount(1, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'superseded']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $later['id'], 'status' => 'confirmed']);
    }

    public function test_attempt_evidence_prevents_sync_task_deletion(): void
    {
        $c = $this->staged();
        $fake = new FakeStoreInventoryPublisher;
        $fake->mode = 'uncertain';
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);

        $this->expectException(QueryException::class);
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

    public function test_destination_publishes_independently_while_source_outcome_is_uncertain(): void
    {
        $c = $this->staged();
        DB::table('inventory_store_sync_tasks')->where('operation_id', $c['task']->operation_id)->where('stage', 'source')->update(['status' => 'uncertain']);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'queued']);
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(1, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed']);
    }

    public function test_transfer_enqueues_both_marketplace_stages_without_a_predecessor_dependency(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $sourceStore = $fixture->store('shopee', 'INDEPENDENT-SOURCE');
        $destinationStore = $fixture->store('lazada', 'INDEPENDENT-DESTINATION');
        $source = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$sourceStore->id);
        $destination = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$destinationStore->id);
        foreach ([$sourceStore, $destinationStore] as $store) {
            DB::table('inventory_store_accounts')->where('account_key', 'marketplace:'.$store->id)
                ->update(['management_status' => 'managed']);
        }
        foreach ([[$source, 8], [$destination, 2]] as [$balance, $quantity]) {
            DB::table('inventory_balances')->where('id', $balance['id'])->update([
                'physical_stock' => $quantity, 'reserved_stock' => 0, 'safety_stock' => 0,
                'counted_at' => now(), 'version' => 1,
            ]);
        }

        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $reviewId = (string) \Illuminate\Support\Str::uuid();
        $operationId = (string) \Illuminate\Support\Str::uuid();
        DB::table('inventory_store_reviews')->insert([
            'id' => $reviewId, 'kind' => 'transfer', 'actor_id' => $actor->id,
            'configuration_version' => 1, 'balance_versions' => '{}', 'payload' => '{}',
            'before' => '{}', 'after' => '{}', 'blockers' => '[]', 'status' => 'confirmed',
            'expires_at' => now(), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_operations')->insert([
            'id' => $operationId, 'review_id' => $reviewId, 'operation_key' => 'independent-stage-test',
            'kind' => 'transfer', 'actor_id' => $actor->id, 'ledger_status' => 'applied',
            'receipt' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $stages = app(SyncOutbox::class)->enqueueTransfer(
            $operationId,
            DB::table('inventory_balances')->find($source['id']),
            DB::table('inventory_balances')->find($destination['id']),
            false,
        );

        $this->assertSame(['queued', 'queued'], array_column($stages, 'status'));
    }

    public function test_operation_status_check_continues_a_destination_after_the_source_is_confirmed(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;
        $c = $this->staged(false, $publisher);
        Bus::fake();
        DB::table('inventory_store_sync_tasks')->where('operation_id', $c['task']->operation_id)
            ->where('stage', 'source')->update(['status' => 'confirmed']);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)
            ->update(['status' => 'waiting_source']);

        $this->actingAs($c['actor'])
            ->postJson('/admin/api/inventory/store-management/operations/'.$c['task']->operation_id.'/check')
            ->assertOk()
            ->assertJsonPath('external_stages.1.status', 'confirmed');

        $this->assertCount(1, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('inventory_sync_observations', [
            'sync_task_id' => $c['task']->id,
            'store_id' => $c['store']->id,
            'product_variant_id' => $c['sku']['variant']->id,
            'quantity' => 6,
        ]);
        Bus::assertNothingDispatched();
    }

    public function test_operation_status_check_does_not_resend_an_explicitly_failed_stage(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;
        $c = $this->staged(false, $publisher);
        Bus::fake();
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update([
            'status' => 'failed',
            'provider_receipt' => json_encode(['code' => 'provider_rejected', 'message' => 'Rejected by provider']),
        ]);

        $this->actingAs($c['actor'])
            ->postJson('/admin/api/inventory/store-management/operations/'.$c['task']->operation_id.'/check')
            ->assertOk()
            ->assertJsonPath('external_stages.1.status', 'failed');

        $this->assertCount(0, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'failed']);
        Bus::assertNothingDispatched();
    }

    public function test_targeted_destination_retry_can_continue_after_the_source_update_was_replaced(): void
    {
        Bus::fake();
        $publisher = new FakeStoreInventoryPublisher;
        $c = $this->staged(false, $publisher);
        DB::table('inventory_store_sync_tasks')->where('operation_id', $c['task']->operation_id)
            ->where('stage', 'source')->update(['status' => 'superseded']);
        DB::table('inventory_store_sync_tasks')->where('id', $c['task']->id)->update(['status' => 'failed']);

        $this->actingAs($c['actor'])
            ->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertCount(1, $publisher->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed']);
        $this->assertDatabaseCount('inventory_store_operations', 1);
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
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['current_stock' => 6, 'physical_stock' => 7, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now()]);
        foreach (['lazada', 'shopee'] as $platform) {
            $store = $fixture->store($platform, strtoupper($platform));
            DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id, 'marketplace_store_id' => $store->id, 'management_status' => 'managed', 'shared_pool_takeover_enabled' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => 'p'.$store->id, 'external_variant_id' => 'v'.$store->id, 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing, 'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $fresh = DB::table('inventory_balances')->find($balance['id']);
        $stages = app(SyncOutbox::class)->enqueueMutation(null, $fresh, 'order', 'shared-test');
        $this->assertCount(2, $stages);
        $this->assertSame([6, 6], array_column($stages, 'target_quantity'));
        $this->assertDatabaseCount('inventory_store_sync_tasks', 2);
    }

    public function test_shared_balance_skips_a_store_after_takeover_is_paused(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['current_stock' => 8, 'counted_at' => now()]);
        $stores = [];
        foreach (['lazada', 'shopee'] as $platform) {
            $store = $fixture->store($platform, strtoupper($platform));
            $stores[] = $store;
            DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id,
                'marketplace_store_id' => $store->id, 'management_status' => 'managed',
                'shared_pool_takeover_enabled' => $platform === 'lazada', 'version' => 1,
                'created_at' => now(), 'updated_at' => now()]);
            $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id,
                'external_product_id' => 'p'.$store->id, 'external_variant_id' => 'v'.$store->id,
                'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing,
                'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id,
                'created_at' => now(), 'updated_at' => now()]);
        }

        $stages = app(SyncOutbox::class)->enqueueMutation(null,
            DB::table('inventory_balances')->find($balance['id']), 'order', 'paused-store-test');
        $this->assertCount(1, $stages);
        $this->assertSame((int) $stores[0]->id, $stages[0]['store_id']);
        $this->assertDatabaseMissing('inventory_store_sync_tasks', ['marketplace_store_id' => $stores[1]->id]);
    }

    public function test_shared_balance_queues_same_total_for_each_enabled_lazada_warehouse(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'WAREHOUSES');
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update(['current_stock' => 17, 'counted_at' => now()]);
        DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id,
            'marketplace_store_id' => $store->id, 'management_status' => 'managed',
            'shared_pool_takeover_enabled' => true, 'version' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id,
            'external_product_id' => '123', 'external_variant_id' => '456',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing,
            'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_facility_scans')->insert(['marketplace_store_id' => $store->id,
            'status' => 'verified', 'topology' => 'multi_warehouse', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2, 3] as $number) {
            DB::table('marketplace_store_facilities')->insert(['marketplace_store_id' => $store->id,
                'provider_facility_id' => 'WH-'.$number, 'name' => 'Warehouse '.$number,
                'is_active' => true, 'shared_pool_takeover_enabled' => $number !== 3,
                'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $stages = app(SyncOutbox::class)->enqueueMutation(null,
            DB::table('inventory_balances')->find($balance['id']), 'order', 'shared-warehouse-test');

        $this->assertCount(2, $stages);
        $this->assertSame([17, 17], array_column($stages, 'target_quantity'));
        $this->assertSame(['WH-1', 'WH-2'], DB::table('inventory_store_sync_tasks')
            ->orderBy('provider_location_id')->pluck('provider_location_id')->all());
        $this->assertNotNull($stages[0]['facility_id']);
        $this->assertNotSame($stages[0]['facility_id'], $stages[1]['facility_id']);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        DB::table('inventory_store_sync_tasks')->where('id', $stages[0]['id'])->update(['status' => 'confirmed']);
        $this->actingAs($actor)->getJson('/admin/api/inventory/store-management/overview')->assertOk()
            ->assertJsonPath('shared_store_sync_states.'.$sku['variant']->id.'.'.$store->id, 'not_synced');
        DB::table('inventory_store_sync_tasks')->where('id', $stages[1]['id'])->update(['status' => 'confirmed']);
        $this->getJson('/admin/api/inventory/store-management/overview')->assertOk()
            ->assertJsonPath('shared_store_sync_states.'.$sku['variant']->id.'.'.$store->id, 'synced');
    }
}
