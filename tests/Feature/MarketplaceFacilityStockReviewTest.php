<?php

namespace Tests\Feature;

use App\Models\User;
use App\Contracts\StoreInventoryPublisher;
use App\Jobs\ProcessFacilityInventorySync;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityStockReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
    }

    public function test_first_count_intake_and_audit_change_only_the_selected_facility_and_replay_is_idempotent(): void
    {
        $c = $this->context();
        $service = app(FacilityStockReview::class);
        $count = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        $this->assertNull(DB::table('inventory_balances')->where('id', $count['balance_id'])->value('physical_stock'));
        $first = $service->confirm($c['actor'], $count['id'], 'count-facility-a');
        $this->assertSame($first, $service->confirm($c['actor'], $count['id'], 'count-facility-a'));
        $this->assertSame(10, $first['after']['physical']);
        $this->assertSame($c['facility_a'], $first['facility_id']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $c['store_id'],
            'marketplace_store_facility_id' => $c['facility_a'],
            'product_variant_id' => $c['variant_id'],
            'provider_location_id' => 'WH-A',
            'target_quantity' => 10,
        ]);

        $intake = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'intake', 5, 'Supplier delivery');
        $this->assertSame(15, $service->confirm($c['actor'], $intake['id'], 'intake-facility-a')['after']['physical']);
        $audit = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'audit', 12, 'Cycle count');
        $this->assertSame(12, $service->confirm($c['actor'], $audit['id'], 'audit-facility-a')['after']['physical']);
        $this->assertSame(12, (int) DB::table('inventory_balances')->where('id', $count['balance_id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.warehouse_id', $c['warehouse_b'])->count());
    }

    public function test_facility_count_selects_new_owner_when_legacy_warehouse_pool_still_exists(): void
    {
        $c = $this->context();
        $warehouseId = DB::table('marketplace_store_facilities')->where('id', $c['facility_a'])
            ->value('inventory_warehouse_id');
        DB::table('inventory_pools')->insert([
            'pool_key' => 'warehouse:'.$warehouseId, 'kind' => 'warehouse',
            'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store_id'],
            $c['facility_a'], $c['variant_id'], 'count', 10, 'Epoch first count');
        $poolKind = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('balance.id', $review['balance_id'])->value('pool.kind');
        $this->assertSame('facility', $poolKind);
    }

    public function test_count_cannot_duplicate_counted_legacy_warehouse_before_epoch_cutover(): void
    {
        $c = $this->context();
        $warehouseId = DB::table('marketplace_store_facilities')->where('id', $c['facility_a'])
            ->value('inventory_warehouse_id');
        $oldPool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'warehouse:'.$warehouseId, 'kind' => 'warehouse',
            'warehouse_id' => $warehouseId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'inventory_pool_id' => $oldPool, 'product_variant_id' => $c['variant_id'],
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FacilityStockReview::class)->review($c['actor'], $c['store_id'],
            $c['facility_a'], $c['variant_id'], 'count', 10, 'Must not duplicate stock');
    }

    public function test_verified_first_count_opens_only_its_exact_facility_order_boundary(): void
    {
        config(['marketplace.multiwarehouse_orders_enabled' => true]);
        $c = $this->context();
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store_id'],
            $c['facility_a'], $c['variant_id'], 'count', 10, 'Verified stock');
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'activate-a');
        $this->assertSame($c['facility_a'], $receipt['order_activation']['facility_id']);
        $this->assertDatabaseHas('inventory_facility_order_activations', [
            'marketplace_store_facility_id' => $c['facility_a'],
            'product_variant_id' => $c['variant_id'],
        ]);
        $this->assertDatabaseCount('inventory_facility_order_activations', 1);
    }

    public function test_stale_review_and_second_first_count_do_not_overwrite_existing_stock(): void
    {
        $c = $this->context();
        $service = app(FacilityStockReview::class);
        $review = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        DB::table('inventory_balances')->where('id', $review['balance_id'])->increment('version');
        try {
            $service->confirm($c['actor'], $review['id'], 'stale-count');
            $this->fail('Stale review must be rejected.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
        }
        $fresh = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        $service->confirm($c['actor'], $fresh['id'], 'fresh-count');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 100, 'Incorrect recount');
    }

    public function test_facility_count_api_requires_admin_and_feature_gate(): void
    {
        $c = $this->context();
        $url = '/admin/api/inventory/store-management/stores/'.$c['store_id']
            .'/facilities/'.$c['facility_a'].'/skus/'.$c['variant_id'].'/stock-reviews';
        $payload = ['kind' => 'count', 'quantity' => 7, 'reason' => 'Initial facility count'];
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url, $payload)->assertUnauthorized();
        config(['marketplace.multiwarehouse_operations_enabled' => false]);
        $this->actingAs($c['actor'])->postJson($url, $payload)->assertNotFound();
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        $response = $this->actingAs($c['actor'])->postJson($url, $payload)->assertCreated();
        $this->postJson('/admin/api/inventory/store-management/facility-stock-reviews/'
            .$response->json('id').'/confirm', ['operation_key' => 'api-count-a'])
            ->assertOk()->assertJsonPath('after.physical', 7);
    }

    public function test_facility_sync_job_sends_only_the_selected_location_and_confirms_exact_readback(): void
    {
        $c = $this->context();
        $service = app(FacilityStockReview::class);
        $review = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        $receipt = $service->confirm($c['actor'], $review['id'], 'sync-count-a');
        $taskId = $receipt['provider_sync_task']['id'];
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(function ($store, $listing, $quantity, $eventKey): bool {
            $this->assertSame('WH-A', $listing->sync_provider_location_id);
            $this->assertTrue($listing->sync_strict_facility);
            $this->assertSame(10, $quantity);

            return true;
        })->andReturn(['provider' => 'lazada', 'observed_quantity' => 10,
            'provider_location_id' => 'WH-A',
            'confirmation_method' => 'provider_readback', 'confirmed_at' => now()->toIso8601String()]);

        config(['marketplace.multiwarehouse_stock_sync_enabled' => true]);
        (new ProcessFacilityInventorySync($taskId))->handle($publisher);

        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $taskId, 'status' => 'confirmed']);
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_facility_sync_never_writes_after_topology_failure_or_balance_change(): void
    {
        $c = $this->context();
        $service = app(FacilityStockReview::class);
        $review = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        $receipt = $service->confirm($c['actor'], $review['id'], 'blocked-count-a');
        $taskId = $receipt['provider_sync_task']['id'];
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        config(['marketplace.multiwarehouse_stock_sync_enabled' => true]);
        app(FacilityRegistry::class)->fail($c['store_id'], 'provider_unavailable');

        (new ProcessFacilityInventorySync($taskId))->handle($publisher);

        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $taskId, 'status' => 'facility_blocked']);
        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $receipt['balance_id'])->value('physical_stock'));
    }

    public function test_confirm_queues_one_facility_sync_when_enabled_and_replay_does_not_queue_twice(): void
    {
        Queue::fake();
        config(['marketplace.multiwarehouse_stock_sync_enabled' => true]);
        $c = $this->context();
        $service = app(FacilityStockReview::class);
        $review = $service->review($c['actor'], $c['store_id'], $c['facility_a'], $c['variant_id'],
            'count', 10, 'Initial physical count');
        $receipt = $service->confirm($c['actor'], $review['id'], 'dispatch-count-a');
        $service->confirm($c['actor'], $review['id'], 'dispatch-count-a');

        Queue::assertPushed(ProcessFacilityInventorySync::class, 1);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $receipt['provider_sync_task']['id'], 'status' => 'facility_pending',
        ]);
    }

    private function context(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'FACILITY-STOCK');
        $variant = $fixture->sku()['variant'];
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
        $warehouses = [];
        foreach (['A', 'B'] as $suffix) {
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-STOCK-'.$suffix, 'name' => 'Stock '.$suffix,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $warehouses[$suffix] = $warehouseId;
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->update(['inventory_warehouse_id' => $warehouseId]);
        }

        return ['actor' => User::factory()->create(['is_admin' => true, 'is_super_admin' => true]),
            'store_id' => (int) $store->id, 'variant_id' => (int) $variant->id,
            'facility_a' => (int) DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-A')->value('id'),
            'warehouse_b' => $warehouses['B']];
    }
}
