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

    public function test_forged_production_seller_cannot_create_a_facility_review(): void
    {
        $c = $this->context();
        DB::table('marketplace_stores')->where('id', $c['store'])->update([
            'account_mode' => 'production', 'external_shop_id' => 'PRODUCTION-SELLER',
        ]);
        $this->expectException(ValidationException::class);
        app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'], $c['variant'],
            'count', 10, 'Forged seller');
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
        $recovery = app(FacilitySyncRecovery::class);
        $this->assertTrue($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'retry-test-a');
        $this->assertSame(7, $this->physical($c['warehouse_a'], $c['variant']));
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_facility_id' => $c['a'],
            'event_key' => 'facility-resync:retry-test-a', 'target_quantity' => 7,
        ]);
    }

    public function test_pending_or_confirmed_test_sync_cannot_be_resynced_directly(): void
    {
        $c = $this->context();
        $review = app(FacilityStockReview::class)->review($c['actor'], $c['store'], $c['a'],
            $c['variant'], 'count', 7, 'Verified test count');
        $receipt = app(FacilityStockReview::class)->confirm($c['actor'], $review['id'], 'count-no-duplicate');
        $recovery = app(FacilitySyncRecovery::class);
        $this->assertFalse($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        try {
            $recovery->resync($c['store'], $c['a'], $c['variant'], 'duplicate-pending');
            $this->fail('Pending stock sync must reject direct resync.');
        } catch (ValidationException) {
            $this->assertSame(1, DB::table('inventory_store_sync_tasks')->where('product_variant_id', $c['variant'])->count());
        }
        DB::table('inventory_store_sync_tasks')->where('id', $receipt['provider_sync_task']['id'])
            ->update(['status' => 'confirmed', 'provider_receipt' => json_encode([
                'provider_location_id' => 'WH-A', 'observed_quantity' => 7,
            ], JSON_THROW_ON_ERROR)]);
        $this->assertFalse($recovery->details($c['store'], $c['a'], $c['variant'])['can_resync']);
        $this->expectException(ValidationException::class);
        $recovery->resync($c['store'], $c['a'], $c['variant'], 'duplicate-confirmed');
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
