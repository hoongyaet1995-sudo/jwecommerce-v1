<?php

namespace Tests\Feature;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Jobs\ProcessStoreInventorySync;
use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function staged(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'SYNC');
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
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, ['kind' => 'transfer', 'source_balance_id' => $source['id'],
            'destination_balance_id' => $destination['id'], 'quantity' => 2, 'reason' => 'Allocation'])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'sync-transfer'])->assertOk()->json();
        $task = DB::table('inventory_store_sync_tasks')->where('operation_id', $receipt['operation_id'])->where('stage', 'destination')->first();

        return compact('actor', 'source', 'destination', 'task');
    }

    public function test_worker_recomputes_latest_atp_before_dispatch_and_restart_does_not_repeat_write(): void
    {
        $c = $this->staged();
        DB::table('inventory_balances')->where('id', $c['destination']['id'])->update(['reserved_stock' => 2, 'version' => DB::raw('version + 1')]);
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertSame(4, $fake->calls[0]['quantity']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'confirmed', 'target_quantity' => 4]);
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(1, $fake->calls);
    }

    public function test_response_lost_is_uncertain_and_requires_reviewed_retry_while_auth_expiry_is_explicit(): void
    {
        $c = $this->staged();
        $fake = new FakeStoreInventoryPublisher;
        $fake->mode = 'uncertain';
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'uncertain']);
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(1, $fake->calls);
        $this->actingAs($c['actor'])->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
            ->assertConflict()->assertJsonPath('error.code', 'reconciliation_required');
        $this->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry', ['reconcile_outcome' => 'not_applied'])
            ->assertOk()->assertJsonPath('status', 'queued');
        $fake->mode = 'auth';
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $c['task']->id, 'status' => 'auth_expired']);
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
}
