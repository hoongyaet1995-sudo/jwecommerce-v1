<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\LazadaTestFacilityAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class BatchFirstCountReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
    }

    public function test_one_review_counts_website_and_both_lazada_facilities_with_exact_conservation(): void
    {
        [$actor, $storeId, $variantId, $facilityIds] = $this->setupSku();
        $allocations = ['website' => 7,
            'facility:'.$storeId.':'.$facilityIds[0] => 10,
            'facility:'.$storeId.':'.$facilityIds[1] => 8];
        $url = '/admin/api/inventory/store-management/batch-first-counts';
        $this->actingAs($actor)->postJson($url, ['variant_id' => $variantId,
            'physical_count' => 24, 'allocations' => $allocations, 'reason' => 'Count mismatch'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('inventory_store_operations', 0);

        $review = $this->postJson($url, ['variant_id' => $variantId,
            'physical_count' => 25, 'allocations' => $allocations, 'reason' => 'Verified batch count'])
            ->assertCreated()->json();
        $confirm = '/admin/api/inventory/store-management/batch-first-counts/'.$review['id'].'/confirm';
        $receipt = $this->postJson($confirm, ['operation_key' => 'batch-count-25'])->assertOk()->json();
        $this->assertSame(25, $receipt['physical_total']);
        $this->assertSame($receipt, $this->postJson($confirm, ['operation_key' => 'batch-count-25'])->assertOk()->json());
        $balances = DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('balance.product_variant_id', $variantId)->whereNotNull('balance.counted_at')
            ->pluck('balance.physical_stock', 'pool.pool_key');
        $this->assertSame(25, $balances->sum());
        $this->assertSame(7, (int) $balances['store:website']);
        $this->assertSame(2, DB::table('inventory_store_sync_tasks')->whereNotNull('marketplace_store_facility_id')->count());
    }

    public function test_a_stale_facility_aborts_the_entire_batch_without_partial_ledger_writes(): void
    {
        [$actor, $storeId, $variantId, $facilityIds] = $this->setupSku();
        $allocations = ['website' => 7,
            'facility:'.$storeId.':'.$facilityIds[0] => 10,
            'facility:'.$storeId.':'.$facilityIds[1] => 8];
        $review = $this->actingAs($actor)->postJson('/admin/api/inventory/store-management/batch-first-counts', [
            'variant_id' => $variantId, 'physical_count' => 25,
            'allocations' => $allocations, 'reason' => 'Atomic batch count',
        ])->assertCreated()->json();
        $facilityReview = DB::table('marketplace_facility_stock_reviews')
            ->where('marketplace_store_facility_id', $facilityIds[1])->first();
        DB::table('inventory_balances')->where('id', $facilityReview->inventory_balance_id)->increment('version');

        $this->postJson('/admin/api/inventory/store-management/batch-first-counts/'.$review['id'].'/confirm', [
            'operation_key' => 'stale-batch-count',
        ])->assertConflict();
        $this->assertDatabaseMissing('inventory_store_operations', ['kind' => 'batch_first_count']);
        $this->assertSame(0, DB::table('inventory_store_sync_tasks')->count());
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('product_variant_id', $variantId)->whereNotNull('counted_at')->count());
    }

    public function test_test_seller_acceptance_allows_sibling_warehouse_sync_tasks_from_the_same_batch(): void
    {
        [$actor, $storeId, $variantId, $facilityIds] = $this->setupSku(true);
        $review = $this->actingAs($actor)->postJson('/admin/api/inventory/store-management/batch-first-counts', [
            'variant_id' => $variantId, 'physical_count' => 25,
            'allocations' => ['website' => 7, 'facility:'.$storeId.':'.$facilityIds[0] => 10,
                'facility:'.$storeId.':'.$facilityIds[1] => 8], 'reason' => 'Test seller batch count',
        ])->assertCreated()->json();
        $this->postJson('/admin/api/inventory/store-management/batch-first-counts/'.$review['id'].'/confirm', [
            'operation_key' => 'test-seller-batch',
        ])->assertOk();
        foreach ($facilityIds as $facilityId) {
            $task = DB::table('inventory_store_sync_tasks')->where('marketplace_store_facility_id', $facilityId)->first();
            $this->assertTrue(app(LazadaTestFacilityAcceptance::class)->inspect(
                $storeId, $variantId, $facilityId, $task->id)['eligible']);
        }
    }

    private function setupSku(bool $testSeller = false): array
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'BATCH-COUNT');
        if ($testSeller) {
            DB::table('marketplace_stores')->where('id', $store->id)->update([
                'account_mode' => 'test', 'environment' => 'live',
                'external_shop_id' => '300163725140', 'data_classification' => 'test',
            ]);
            config(['marketplace.multiwarehouse_test_acceptance_enabled' => true,
                'marketplace.lazada.test_store_id' => (string) $store->id]);
        }
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
                'code' => 'JW-BATCH-'.$suffix, 'name' => 'Batch '.$suffix,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->update(['inventory_warehouse_id' => $warehouseId]);
            $ids[] = (int) DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->value('id');
        }

        return [User::factory()->create(['is_admin' => true, 'is_super_admin' => true]),
            (int) $store->id, (int) $variant->id, $ids];
    }
}
