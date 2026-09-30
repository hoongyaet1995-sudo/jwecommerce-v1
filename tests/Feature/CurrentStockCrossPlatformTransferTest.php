<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityTransferReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class CurrentStockCrossPlatformTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_current_stock_transfer_can_move_between_shopee_website_and_lazada_facility(): void
    {
        $c = $this->context();
        $transfer = app(FacilityTransferReview::class);
        $toWebsite = $transfer->review($c['actor'], $c['variant'], null, null, 2,
            'Shopee to website', $c['shopee'], null);
        $transfer->confirm($c['actor'], $toWebsite['id'], 'cross-shopee-website');
        $toLazada = $transfer->review($c['actor'], $c['variant'], null, $c['facility'], 3,
            'Shopee to Lazada', $c['shopee'], $c['lazada']);
        $receipt = $transfer->confirm($c['actor'], $toLazada['id'], 'cross-shopee-lazada');
        $this->assertSame('applied', $receipt['status']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['shopee_balance'], 'current_stock' => 5]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['website_balance'], 'current_stock' => 2]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['lazada_balance'], 'current_stock' => 4]);
        $this->assertSame(0, (int) DB::table('inventory_current_stock_movements')
            ->whereIn('inventory_balance_id', [$c['shopee_balance'], $c['website_balance'], $c['lazada_balance']])
            ->sum('delta')); // Transfers conserve stock; opening balances were seeded directly.
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $c['shopee'], 'target_quantity' => 5,
        ]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $c['lazada'], 'marketplace_store_facility_id' => $c['facility'],
            'target_quantity' => 4,
        ]);
    }

    public function test_uncounted_destination_is_rejected_without_any_movement(): void
    {
        $c = $this->context();
        DB::table('inventory_balances')->where('id', $c['website_balance'])
            ->update(['current_stock' => null, 'counted_at' => null]);
        $this->expectException(ValidationException::class);
        try {
            app(FacilityTransferReview::class)->review($c['actor'], $c['variant'], null, null, 1,
                'Not counted', $c['shopee'], null);
        } finally {
            $this->assertDatabaseCount('inventory_current_stock_movements', 0);
        }
    }

    public function test_lazada_facility_can_transfer_to_shopee_direct_stock(): void
    {
        $c = $this->context();
        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], $c['facility'], null, 1,
            'Lazada to Shopee', $c['lazada'], $c['shopee']);
        $receipt = $transfer->confirm($c['actor'], $review['id'], 'cross-lazada-shopee');
        $this->assertSame($receipt, $transfer->confirm($c['actor'], $review['id'], 'cross-lazada-shopee'));
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['lazada_balance'], 'current_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['shopee_balance'], 'current_stock' => 11]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $c['shopee'], 'target_quantity' => 11,
        ]);
        $this->assertSame(0, (int) DB::table('inventory_current_stock_movements')
            ->whereIn('inventory_balance_id', [$c['shopee_balance'], $c['lazada_balance']])->sum('delta'));
    }

    public function test_website_can_transfer_to_lazada_facility_after_first_count(): void
    {
        $c = $this->context();
        DB::table('inventory_balances')->where('id', $c['website_balance'])->update(['current_stock' => 3]);
        $transfer = app(FacilityTransferReview::class);
        $review = $transfer->review($c['actor'], $c['variant'], null, $c['facility'], 2,
            'Website to Lazada', null, $c['lazada']);
        $transfer->confirm($c['actor'], $review['id'], 'cross-website-lazada');
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['website_balance'], 'current_stock' => 1]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['lazada_balance'], 'current_stock' => 3]);
    }

    private function context(): array
    {
        Queue::fake();
        config(['inventory.current_stock_only' => true,
            'marketplace.multiwarehouse_operations_enabled' => true,
            'marketplace.multiwarehouse_stock_sync_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $shopee = $fixture->store('shopee', 'CROSS');
        $lazada = $fixture->store('lazada', 'CROSS');
        app(FacilityRegistry::class)->confirm((int) $shopee->id, []);
        app(FacilityRegistry::class)->confirm((int) $lazada->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $lazada->id)->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'CROSS-WH-A', 'name' => 'A', 'is_active' => true,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        foreach ([$shopee, $lazada] as $store) {
            $listing = DB::table('marketplace_listing_variants')->insertGetId([
                'store_id' => $store->id, 'external_product_id' => '100',
                'external_variant_id' => '200', 'observed_data' => '{}',
                'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_variant_mappings')->insert([
                'store_id' => $store->id, 'listing_variant_id' => $listing,
                'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $shopeeBalance = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$shopee->id);
        $websiteBalance = app(AccountResolver::class)->balance($variant->id, 'website');
        DB::table('inventory_balances')->where('id', $shopeeBalance['id'])
            ->update(['current_stock' => 10, 'counted_at' => now()]);
        DB::table('inventory_balances')->where('id', $websiteBalance['id'])
            ->update(['current_stock' => 0, 'counted_at' => now()]);
        $pool = DB::table('inventory_pools')->insertGetId([
            'pool_key' => 'facility:'.$warehouse, 'kind' => 'facility', 'warehouse_id' => $warehouse,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $lazadaBalance = DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $pool, 'product_variant_id' => $variant->id,
            'current_stock' => 1, 'counted_at' => now(), 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('marketplace_store_id', $shopee->id)
            ->update(['management_status' => 'managed']);
        return ['actor' => $actor, 'variant' => (int) $variant->id,
            'shopee' => (int) $shopee->id, 'lazada' => (int) $lazada->id,
            'facility' => (int) $facility->id,
            'shopee_balance' => (int) $shopeeBalance['id'],
            'website_balance' => (int) $websiteBalance['id'], 'lazada_balance' => $lazadaBalance];
    }
}
