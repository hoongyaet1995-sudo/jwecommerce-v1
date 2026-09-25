<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use App\Services\Inventory\StoreManagement\FacilityTransferReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityTransferReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_store_transfer_moves_only_unreserved_stock_between_exact_facilities(): void
    {
        $c = $this->context();
        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], $c['a'], $c['b'], 3, 'Rebalance stock');
        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $c['balance_a'])->value('physical_stock'));
        $receipt = $transfer->confirm($c['actor'], $review['id'], 'move-3-a-b');
        $this->assertSame($receipt, $transfer->confirm($c['actor'], $review['id'], 'move-3-a-b'));
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('id', $c['balance_a'])->value('physical_stock'));
        $this->assertSame(8, (int) DB::table('inventory_balances')->where('id', $c['balance_b'])->value('physical_stock'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['provider_location_id' => 'WH-A', 'target_quantity' => 5]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['provider_location_id' => 'WH-B', 'target_quantity' => 8]);
        $this->assertSame('applied', $receipt['status']);
    }

    public function test_transfer_rejects_reserved_floor_same_owner_and_stale_version(): void
    {
        $c = $this->context();
        $transfer = app(FacilityTransferReview::class);
        try {
            $transfer->review($c['actor'], $c['variant'], $c['a'], $c['b'], 9, 'Too much');
            $this->fail('Reserved floor ignored');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $transfer->review($c['actor'], $c['variant'], $c['a'], $c['a'], 1, 'Same owner');
    }

    public function test_confirm_rejects_stale_source_without_partial_move(): void
    {
        $c = $this->context();
        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], $c['a'], $c['b'], 2, 'Move after review');
        DB::table('inventory_balances')->where('id', $c['balance_a'])->increment('version');
        try {
            $transfer->confirm($c['actor'], $review['id'], 'stale-move');
            $this->fail('Stale balance accepted');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
        }
        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $c['balance_a'])->value('physical_stock'));
        $this->assertSame(5, (int) DB::table('inventory_balances')->where('id', $c['balance_b'])->value('physical_stock'));
    }

    public function test_facility_to_website_transfer_moves_both_ledgers_and_syncs_only_facility(): void
    {
        $c = $this->context();
        $websitePool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $websiteBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $websitePool, 'product_variant_id' => $c['variant'],
            'physical_stock' => 4, 'reserved_stock' => 0, 'safety_stock' => 0,
            'counted_at' => now(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], $c['a'], null, 3, 'Move to website');
        $receipt = $transfer->confirm($c['actor'], $review['id'], 'facility-to-website');

        $this->assertSame('applied', $receipt['status']);
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('id', $c['balance_a'])->value('physical_stock'));
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('id', $websiteBalance)->value('physical_stock'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['provider_location_id' => 'WH-A', 'target_quantity' => 5]);
        $this->assertSame(1, DB::table('inventory_store_sync_tasks')->count());
        $this->assertSame('confirmed', $receipt['sync'][1]['status']);
    }

    public function test_website_to_facility_transfer_respects_website_reservations(): void
    {
        $c = $this->context();
        $websitePool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'store:website', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $websiteBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $websitePool, 'product_variant_id' => $c['variant'],
            'physical_stock' => 10, 'reserved_stock' => 3, 'safety_stock' => 1,
            'counted_at' => now(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $transfer = app(FacilityTransferReview::class);
        try {
            $transfer->review($c['actor'], $c['variant'], null, $c['a'], 7, 'Too much');
            $this->fail('Reserved website stock was transferred');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $review = $transfer->review($c['actor'], $c['variant'], null, $c['a'], 5, 'Restock Lazada');
        $receipt = $transfer->confirm($c['actor'], $review['id'], 'website-to-facility');
        $this->assertSame('applied', $receipt['status']);
        $this->assertSame(5, (int) DB::table('inventory_balances')->where('id', $websiteBalance)->value('physical_stock'));
        $this->assertSame(15, (int) DB::table('inventory_balances')->where('id', $c['balance_a'])->value('physical_stock'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['provider_location_id' => 'WH-A', 'target_quantity' => 13]);
    }

    private function context(): array
    {
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'TRANSFER-FACILITIES');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        foreach (['A' => 10, 'B' => 5] as $code => $physical) {
            $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$code)->first();
            $warehouse = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-TRANSFER-'.$code, 'name' => 'JW '.$code,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('id', $facility->id)
                ->update(['inventory_warehouse_id' => $warehouse]);
            $pool = DB::table('inventory_pools')->insertGetId([
                'pool_key' => 'facility:'.$warehouse, 'kind' => 'facility', 'warehouse_id' => $warehouse,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $balance = DB::table('inventory_balances')->insertGetId([
                'inventory_pool_id' => $pool, 'product_variant_id' => $variant->id,
                'physical_stock' => $physical, 'reserved_stock' => $code === 'A' ? 2 : 0,
                'safety_stock' => 0, 'counted_at' => now(), 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $ids[$code] = ['facility' => (int) $facility->id, 'balance' => $balance];
        }

        return ['actor' => $actor, 'variant' => (int) $variant->id,
            'a' => $ids['A']['facility'], 'b' => $ids['B']['facility'],
            'balance_a' => $ids['A']['balance'], 'balance_b' => $ids['B']['balance']];
    }
}
