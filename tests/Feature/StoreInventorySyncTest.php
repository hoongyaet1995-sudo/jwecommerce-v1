<?php

namespace Tests\Feature;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Jobs\ProcessStoreInventorySync;
use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\SyncOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use Mockery;

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
        (new ProcessStoreInventorySync($c['task']->id))->handle($fake);
        $this->assertCount(1, $fake->calls);
        $reconciler = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $reconciler->shouldReceive('observe')->once()->andReturn([
            'provider' => 'lazada', 'observed_quantity' => 3, 'observed_at' => now()->toIso8601String(),
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $reconciler);
        $this->actingAs($c['actor'])->postJson('/admin/api/inventory/store-management/sync-tasks/'.$c['task']->id.'/retry')
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

    public function test_mapping_or_canary_change_after_enqueue_supersedes_without_transport(): void
    {
        $mapping = $this->staged();
        DB::table('marketplace_variant_mappings')->where('listing_variant_id', $mapping['listingId'])->delete();
        $fake = new FakeStoreInventoryPublisher;
        (new ProcessStoreInventorySync($mapping['task']->id))->handle($fake);
        $this->assertCount(0, $fake->calls);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $mapping['task']->id, 'status' => 'superseded']);

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
