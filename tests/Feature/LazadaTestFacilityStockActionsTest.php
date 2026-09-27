<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use App\Services\Inventory\StoreManagement\FacilityTransferReview;
use App\Services\Inventory\StoreManagement\FacilitySyncRecovery;
use App\Contracts\StoreInventoryPublisher;
use App\Jobs\ProcessFacilityInventorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LazadaTestFacilityStockActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_test_acceptance_enabled' => true,
            'marketplace.multiwarehouse_operations_enabled' => false]);
        DB::table('inventory_configuration')->where('id', 1)
            ->update(['operating_mode' => 'independent_stores']);
    }

    public function test_four_actions_mutate_only_test_facility_balances(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        foreach ([[$c['a'], 10, 'a'], [$c['b'], 4, 'b']] as [$facility, $quantity, $key]) {
            $review = $stock->review($c['actor'], $c['store'], $facility, $c['variant'],
                'count', $quantity, 'Verified test count');
            $stock->confirm($c['actor'], $review['id'], 'count-'.$key);
            DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $facility)
                ->update(['status' => 'confirmed']);
        }
        $intake = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'intake', 3, 'Supplier receipt');
        $stock->confirm($c['actor'], $intake['id'], 'intake-a');
        DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $c['a'])
            ->update(['status' => 'confirmed']);
        $audit = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'audit', 11, 'Cycle count');
        $stock->confirm($c['actor'], $audit['id'], 'audit-a');
        DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $c['a'])
            ->update(['status' => 'confirmed']);
        $transfer = app(FacilityTransferReview::class)->review($c['actor'], $c['variant'],
            $c['a'], $c['b'], 2, 'Warehouse transfer');
        $receipt = app(FacilityTransferReview::class)->confirm($c['actor'], $transfer['id'], 'transfer-a-b');
        $this->assertSame($receipt, app(FacilityTransferReview::class)
            ->confirm($c['actor'], $transfer['id'], 'transfer-a-b'));
        $this->assertSame(9, $this->physical($c['warehouse_a'], $c['variant']));
        $this->assertSame(6, $this->physical($c['warehouse_b'], $c['variant']));
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->twice()->andReturnUsing(
            fn ($store, $listing, $quantity): array => [
                'provider' => 'lazada', 'observed_quantity' => $quantity,
                'provider_location_id' => $listing->sync_provider_location_id,
                'confirmation_method' => 'provider_readback',
                'confirmed_at' => now()->toIso8601String(),
            ]);
        $tasks = DB::table('inventory_store_sync_tasks')->where('product_variant_id', $c['variant'])
            ->where('status', 'facility_pending')->orderBy('id')->pluck('id');
        $this->assertCount(2, $tasks);
        foreach ($tasks as $taskId) {
            (new ProcessFacilityInventorySync($taskId))->handle($publisher);
        }
        $this->assertSame(2, DB::table('inventory_store_sync_tasks')
            ->whereIn('id', $tasks)->where('status', 'confirmed')->count());
    }

    public function test_website_and_lazada_test_warehouse_can_transfer_after_count(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        $count = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 10, 'Verified warehouse count');
        $stock->confirm($c['actor'], $count['id'], 'mixed-count-a');
        DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $c['a'])
            ->update(['status' => 'confirmed']);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $websiteBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $c['variant'],
            'physical_stock' => 5, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], $c['a'], null, 2, 'To website');
        $receipt = $transfer->confirm($c['actor'], $review['id'], 'test-facility-to-website');
        $this->assertSame('applied', $receipt['status']);
        $this->assertSame(8, $this->physical($c['warehouse_a'], $c['variant']));
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('id', $websiteBalance)->value('physical_stock'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_facility_id' => $c['a'], 'target_quantity' => 8,
        ]);
    }

    public function test_production_seller_requires_general_gate_but_uses_the_same_facility_review(): void
    {
        $c = $this->context();
        DB::table('marketplace_stores')->where('id', $c['store'])->update([
            'account_mode' => 'production', 'external_shop_id' => 'PRODUCTION-SELLER',
        ]);
        try {
            app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'], $c['variant'],
                'count', 10, 'Production facility count');
            $this->fail('Production must remain closed while general facility operations are disabled.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('marketplace_facility_stock_reviews', 0);
        }
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 10, 'Production facility count');
        $this->assertSame('pending', $review['status']);
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'production-count');
        $this->assertSame(10, $receipt['after']['physical']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $c['store'], 'marketplace_store_facility_id' => $c['a'],
            'product_variant_id' => $c['variant'], 'target_quantity' => 10,
        ]);
    }

    public function test_test_only_sync_writes_and_reads_back_exact_lazada_warehouse(): void
    {
        $c = $this->context();
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'],
            $c['variant'], 'count', 7, 'Verified test count');
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'count-sync-a');
        $taskId = $receipt['provider_sync_task']['id'];
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(function ($store, $listing, $quantity): bool {
            $this->assertSame('300163725140', $store->external_shop_id);
            $this->assertSame('WH-A', $listing->sync_provider_location_id);
            $this->assertTrue($listing->sync_strict_facility);
            $this->assertSame(7, $quantity);

            return true;
        })->andReturn(['provider' => 'lazada', 'observed_quantity' => 7,
            'provider_location_id' => 'WH-A',
            'confirmation_method' => 'provider_readback', 'confirmed_at' => now()->toIso8601String()]);

        (new ProcessFacilityInventorySync($taskId))->handle($publisher);

        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $taskId, 'status' => 'confirmed', 'provider_location_id' => 'WH-A',
        ]);
    }

    public function test_test_only_failed_sync_can_be_reviewed_and_resynced_without_a_second_count(): void
    {
        $c = $this->context();
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'],
            $c['variant'], 'count', 7, 'Verified test count');
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'count-resync-a');
        DB::table('inventory_store_sync_tasks')->where('id', $receipt['provider_sync_task']['id'])
            ->update(['status' => 'failed']);
        $eligibility = app(\App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::class)
            ->inspect($c['store'], $c['variant'], $c['a']);
        $this->assertNotContains('unfinished_stock_sync', array_column($eligibility['blockers'], 'code'));
        $recovery = app(FacilitySyncRecovery::class);
        $this->assertTrue($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'retry-test-a');
        $this->assertSame(7, $this->physical($c['warehouse_a'], $c['variant']));
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_facility_id' => $c['a'],
            'event_key' => 'facility-resync:retry-test-a', 'target_quantity' => 7,
        ]);
    }

    public function test_older_pending_sync_does_not_block_stock_review_after_a_newer_failure(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        $review = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 7, 'Verified test count');
        $receipt = $stock->confirm($c['actor'], $review['id'], 'count-stale-task');
        $oldTaskId = $receipt['provider_sync_task']['id'];
        DB::table('inventory_store_sync_tasks')->where('id', $oldTaskId)
            ->update(['status' => 'facility_pending']);
        $newTask = (array) DB::table('inventory_store_sync_tasks')->where('id', $oldTaskId)->first();
        $newTask['id'] = (string) \Illuminate\Support\Str::uuid();
        $newTask['event_key'] = 'facility-resync:newer-failed-task';
        $newTask['status'] = 'failed';
        $newTask['created_at'] = now()->addSecond();
        $newTask['updated_at'] = now()->addSecond();
        DB::table('inventory_store_sync_tasks')->insert($newTask);

        $eligibility = app(\App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::class)
            ->inspect($c['store'], $c['variant'], $c['a']);
        $this->assertNotContains('unfinished_stock_sync', array_column($eligibility['blockers'], 'code'));
        $audit = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'audit', 9, 'Reviewed physical count');
        $this->assertSame('pending', $audit['status']);

        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        (new ProcessFacilityInventorySync($oldTaskId))->handle($publisher);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $oldTaskId, 'status' => 'superseded']);
    }

    public function test_current_pending_sync_is_advisory_for_stock_management(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        $review = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 7, 'Verified test count');
        $stock->confirm($c['actor'], $review['id'], 'count-current-pending');

        $eligibility = app(\App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance::class)
            ->inspect($c['store'], $c['variant'], $c['a']);
        $this->assertContains('unfinished_stock_sync', array_column($eligibility['warnings'], 'code'));
        $this->assertTrue($eligibility['eligible']);
    }

    public function test_pending_and_confirmed_test_sync_can_be_resend_without_changing_jw_stock(): void
    {
        $c = $this->context();
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'],
            $c['variant'], 'count', 7, 'Verified test count');
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'count-no-duplicate');
        $recovery = app(FacilitySyncRecovery::class);
        $this->assertTrue($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'duplicate-pending', $c['actor']->id,
            'Operator requested the current absolute quantity again');
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $receipt['provider_sync_task']['id'], 'status' => 'superseded',
        ]);
        $this->assertSame(7, $this->physical($c['warehouse_a'], $c['variant']));
        $resent = DB::table('inventory_store_sync_tasks')->where('event_key', 'facility-resync:duplicate-pending')->first();
        $this->assertSame(7, (int) $resent->target_quantity);
        $this->assertDatabaseHas('inventory_store_sync_attempts', [
            'task_id' => $resent->id, 'actor_id' => $c['actor']->id, 'source' => 'manual',
        ]);
        DB::table('inventory_store_sync_tasks')->where('id', $resent->id)
            ->update(['status' => 'confirmed', 'provider_receipt' => json_encode([
                'provider_location_id' => 'WH-A', 'observed_quantity' => 7,
            ], JSON_THROW_ON_ERROR)]);
        $this->assertTrue($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'duplicate-confirmed', $c['actor']->id);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'event_key' => 'facility-resync:duplicate-confirmed', 'target_quantity' => 7,
        ]);
    }

    public function test_uncertain_provider_result_can_be_superseded_by_manual_absolute_resend(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        $review = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 7, 'Verified test count');
        $receipt = $stock->confirm($c['actor'], $review['id'], 'count-uncertain-resend');
        DB::table('inventory_store_sync_tasks')->where('id', $receipt['provider_sync_task']['id'])
            ->update(['status' => 'uncertain']);

        $recovery = app(FacilitySyncRecovery::class);
        $this->assertTrue($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'manual-after-uncertain', $c['actor']->id);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $receipt['provider_sync_task']['id'], 'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'event_key' => 'facility-resync:manual-after-uncertain', 'target_quantity' => 7,
            'status' => 'facility_pending',
        ]);
    }

    public function test_manual_resend_waits_for_an_active_write_then_sends_latest_absolute_quantity(): void
    {
        $c = $this->context();
        $stock = app(FacilityStockReview::class);
        $review = $stock->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 7, 'Verified test count');
        $receipt = $stock->confirm($c['actor'], $review['id'], 'count-active-resend');
        $oldId = $receipt['provider_sync_task']['id'];
        DB::table('inventory_store_sync_tasks')->where('id', $oldId)->update(['status' => 'processing']);
        \Illuminate\Support\Facades\Queue::fake();

        app(FacilitySyncRecovery::class)->resync($c['store'], $c['a'], $c['variant'],
            'manual-while-processing', $c['actor']->id);
        $newId = DB::table('inventory_store_sync_tasks')
            ->where('event_key', 'facility-resync:manual-while-processing')->value('id');
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        (new ProcessFacilityInventorySync($newId))->handle($publisher);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $newId, 'status' => 'facility_pending']);

        DB::table('inventory_store_sync_tasks')->where('id', $oldId)->update(['status' => 'confirmed']);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturnUsing(
            fn ($store, $listing, $quantity): array => [
                'provider_location_id' => $listing->sync_provider_location_id,
                'observed_quantity' => $quantity, 'confirmation_method' => 'provider_readback',
            ]);
        (new ProcessFacilityInventorySync($newId))->handle($publisher);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $newId, 'status' => 'confirmed']);
    }

    private function physical(int $warehouseId, int $variantId): int
    {
        return (int) DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->where('pool.warehouse_id', $warehouseId)
            ->where('balance.product_variant_id', $variantId)->value('balance.physical_stock');
    }

    private function context(): array
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ISOLATED-ACTIONS');
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'account_mode' => 'test', 'environment' => 'live',
            'external_shop_id' => '300163725140', 'data_classification' => 'test',
        ]);
        config(['marketplace.lazada.test_store_id' => (string) $store->id]);
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
        $ids = [];
        foreach (['A', 'B'] as $suffix) {
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-TEST-'.$suffix, 'name' => 'Test '.$suffix,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $facilityId = (int) DB::table('marketplace_store_facilities')
                ->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->value('id');
            DB::table('marketplace_store_facilities')->where('id', $facilityId)
                ->update(['inventory_warehouse_id' => $warehouseId]);
            $ids[strtolower($suffix)] = $facilityId;
            $ids['warehouse_'.strtolower($suffix)] = $warehouseId;
        }

        return ['actor' => User::factory()->create(['is_admin' => true, 'is_super_admin' => true]),
            'store' => (int) $store->id, 'variant' => (int) $variant->id] + $ids;
    }
}
