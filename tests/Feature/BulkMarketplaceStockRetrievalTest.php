<?php

namespace Tests\Feature;

use App\Jobs\ProcessBulkMarketplaceStockRetrieval;
use App\Models\User;
use App\Services\Inventory\CurrentStockCutover;
use App\Services\Inventory\StoreManagement\BulkMarketplaceStockRetrieval;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\MarketplaceBaselineFirstCount;
use App\Services\Inventory\StoreManagement\MarketplaceItemStockSnapshot;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class BulkMarketplaceStockRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_distinguishes_verified_lazada_zero_stock_from_missing_shopee_stock(): void
    {
        $reader = app(MarketplaceItemStockSnapshot::class);
        $snapshot = ['stock' => ['200' => ['WH-A' => 12]]];
        $listing = (object) ['external_variant_id' => '200'];
        $facility = (object) ['provider_facility_id' => 'WH-B'];
        $this->assertSame(0, $reader->quantity($snapshot, $listing, $facility, 'lazada'));
        $this->expectException(\RuntimeException::class);
        $reader->quantity($snapshot, $listing, $facility, 'shopee');
    }

    public function test_one_item_snapshot_reconciles_multiple_skus_and_warehouses_with_two_provider_reads(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'BULK-GROUP');
        $variants = [$fixture->sku()['variant'], $fixture->sku()['variant']];
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        foreach (['WH-A', 'WH-B'] as $code) {
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-GROUP-'.$code, 'name' => 'Group '.$code,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', $code)->update(['inventory_warehouse_id' => $warehouseId]);
        }
        foreach ($variants as $index => $variant) {
            $listingId = DB::table('marketplace_listing_variants')->insertGetId([
                'store_id' => $store->id, 'external_product_id' => '100',
                'external_variant_id' => (string) (200 + $index), 'observed_data' => '{}',
                'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_variant_mappings')->insert([
                'store_id' => $store->id, 'listing_variant_id' => $listingId,
                'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $snapshot = Mockery::mock(MarketplaceItemStockSnapshot::class)->makePartial();
        $snapshot->shouldReceive('read')->twice()->withArgs(fn ($s, $id) => (int) $s->id === (int) $store->id && $id === '100')
            ->andReturn(['stock' => ['200' => ['WH-A' => 10, 'WH-B' => 20],
                '201' => ['WH-A' => 30, 'WH-B' => 40]], 'observed_at' => '2026-09-29T10:00:00+00:00']);
        $this->app->instance(MarketplaceItemStockSnapshot::class, $snapshot);
        Queue::fake();
        $run = app(BulkMarketplaceStockRetrieval::class)->start($actor,
            array_map(fn ($variant) => (int) $variant->id, $variants));
        (new ProcessBulkMarketplaceStockRetrieval($run['id']))->handle(app(MarketplaceBaselineFirstCount::class), $snapshot);
        $status = app(BulkMarketplaceStockRetrieval::class)->status($actor, $run['id']);
        $this->assertSame('succeeded', $status['status']);
        $this->assertSame(4, $status['completed_locations']);
        $this->assertSame(2, $status['completed_skus']);
        $this->assertDatabaseCount('inventory_current_stock_movements', 4);
        $this->assertEqualsCanonicalizing([10, 20, 30, 40], DB::table('inventory_balances')
            ->whereIn('product_variant_id', array_map(fn ($variant) => $variant->id, $variants))
            ->whereNotNull('current_stock')->pluck('current_stock')->map(fn ($value) => (int) $value)->all());

        $changed = Mockery::mock(MarketplaceItemStockSnapshot::class)->makePartial();
        $changed->shouldReceive('read')->twice()->andReturn(
            ['stock' => ['200' => ['WH-A' => 10, 'WH-B' => 20],
                '201' => ['WH-A' => 30, 'WH-B' => 40]], 'observed_at' => '2026-09-29T10:01:00+00:00'],
            ['stock' => ['200' => ['WH-A' => 11, 'WH-B' => 20],
                '201' => ['WH-A' => 30, 'WH-B' => 40]], 'observed_at' => '2026-09-29T10:01:01+00:00'],
        );
        $secondRun = app(BulkMarketplaceStockRetrieval::class)->start($actor,
            array_map(fn ($variant) => (int) $variant->id, $variants));
        (new ProcessBulkMarketplaceStockRetrieval($secondRun['id']))->handle(app(MarketplaceBaselineFirstCount::class), $changed);
        $secondStatus = app(BulkMarketplaceStockRetrieval::class)->status($actor, $secondRun['id']);
        $this->assertSame('completed_with_errors', $secondStatus['status']);
        $this->assertSame(1, $secondStatus['failed_locations']);
        $this->assertDatabaseHas('marketplace_stock_retrieval_items', [
            'run_id' => $secondRun['id'], 'status' => 'failed', 'error_code' => 'stock_changed_during_retrieval',
        ]);
    }

    public function test_worker_automatically_first_counts_then_audits_exact_warehouse_without_provider_push(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'BULK-TEST');
        $variant = $fixture->sku()['variant'];
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouseId = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-BULK-A', 'name' => 'Bulk A', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->update(['inventory_warehouse_id' => $warehouseId]);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->times(6)->andReturn(
            ['observed_quantity' => 20, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:00:00+00:00'],
            ['observed_quantity' => 20, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:00:01+00:00'],
            ['observed_quantity' => 18, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:01:00+00:00'],
            ['observed_quantity' => 18, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:01:01+00:00'],
            ['observed_quantity' => 17, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:02:00+00:00'],
            ['observed_quantity' => 16, 'provider_location_id' => 'WH-A', 'observed_at' => '2026-09-29T10:02:01+00:00'],
        );
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        Queue::fake();
        $service = app(BulkMarketplaceStockRetrieval::class);
        $run = $service->start($actor, [$variant->id]);
        Queue::assertPushed(ProcessBulkMarketplaceStockRetrieval::class);
        $this->actingAs($actor)->getJson('/admin/api/inventory/store-management/stock-retrieval-runs/latest')
            ->assertOk()->assertJsonPath('id', $run['id']);
        $this->runToCompletion($run['id']);
        $this->assertSame('succeeded', $service->status($actor, $run['id'])['status']);
        $this->assertDatabaseHas('marketplace_stock_retrieval_items', ['run_id' => $run['id'], 'action' => 'first_count',
            'observed_quantity' => 20, 'status' => 'succeeded']);

        $second = $service->start($actor, [$variant->id]);
        $this->runToCompletion($second['id']);
        $this->assertDatabaseHas('marketplace_stock_retrieval_items', ['run_id' => $second['id'],
            'action' => 'audit_adjustment', 'previous_quantity' => 20, 'observed_quantity' => 18]);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id, 'current_stock' => 18]);
        $this->assertDatabaseCount('inventory_current_stock_movements', 2);
        $balanceId = (int) DB::table('inventory_balances')->where('product_variant_id', $variant->id)
            ->where('current_stock', 18)->value('id');
        $this->assertTrue(app(CurrentStockCutover::class)->isHistorical('marketplace:'.$store->id,
            'DELAYED-ORDER', '2026-09-29T10:00:30+00:00', $balanceId));

        $third = $service->start($actor, [$variant->id]);
        $this->runToCompletion($third['id']);
        $this->assertSame('completed_with_errors', $service->status($actor, $third['id'])['status']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id, 'current_stock' => 18]);
        $freshPublisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $freshPublisher->shouldReceive('observe')->twice()->andReturn([
            'observed_quantity' => 16, 'provider_location_id' => 'WH-A',
            'observed_at' => '2026-09-29T10:03:00+00:00',
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $freshPublisher);
        $service->resume($actor, $third['id']);
        $this->runToCompletion($third['id']);
        $this->assertSame('succeeded', $service->status($actor, $third['id'])['status']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id, 'current_stock' => 16]);
        $this->assertDatabaseCount('inventory_current_stock_movements', 3);
    }

    private function runToCompletion(string $runId): void
    {
        $job = new ProcessBulkMarketplaceStockRetrieval($runId);
        $job->handle(app(MarketplaceBaselineFirstCount::class));
        (new ProcessBulkMarketplaceStockRetrieval($runId))->handle(app(MarketplaceBaselineFirstCount::class));
    }
}
