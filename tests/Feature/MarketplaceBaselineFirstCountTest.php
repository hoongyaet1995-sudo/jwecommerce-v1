<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\CurrentStockCutover;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\MarketplaceBaselineFirstCount;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceBaselineFirstCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_facility_import_creates_only_its_current_stock_without_stock_push(): void
    {
        $c = $this->context();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->twice()->andReturn([
            'observed_quantity' => 20, 'provider_location_id' => 'WH-A',
            'observed_at' => '2026-09-29T10:00:00+00:00',
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $service = app(MarketplaceBaselineFirstCount::class);
        $read = $service->observe($c['actor'], (string) $c['store_id'], $c['facility_a'], $c['variant_id']);
        $this->assertSame(20, $read['quantity']);
        $receipt = $service->commit($c['actor'], (string) $c['store_id'], $c['facility_a'],
            $c['variant_id'], 'marketplace', 20, 20, 'baseline-test-a');
        $this->assertSame('matched_at_import', $receipt['provider_sync_status']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $c['variant_id'], 'current_stock' => 20]);
        $this->assertSame('2026-09-29 10:00:00', DB::table('inventory_balances')
            ->where('product_variant_id', $c['variant_id'])->where('current_stock', 20)->value('counted_at'));
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_facility_id' => $c['facility_a'], 'status' => 'confirmed',
            'target_quantity' => 20, 'attempts' => 0,
        ]);
        $this->assertDatabaseCount('inventory_current_stock_movements', 1);
        $this->assertTrue(app(CurrentStockCutover::class)->isHistorical('marketplace:'.$c['store_id'],
            'OLD-ORDER', '2026-09-29T09:59:59+00:00', (int) DB::table('inventory_balances')
                ->where('product_variant_id', $c['variant_id'])->value('id')));
    }

    public function test_changed_provider_quantity_rejects_stale_baseline_without_ledger_write(): void
    {
        $c = $this->context();
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 19, 'provider_location_id' => 'WH-A',
            'observed_at' => '2026-09-29T10:00:00+00:00',
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        try {
            app(MarketplaceBaselineFirstCount::class)->commit($c['actor'], (string) $c['store_id'],
                $c['facility_a'], $c['variant_id'], 'marketplace', 20, 20, 'baseline-stale');
            $this->fail('A stale provider read must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stock', $exception->errors());
        }
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
    }

    public function test_shopee_default_inventory_import_counts_the_store_ledger_without_a_push(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('shopee', 'BASELINE-DIRECT');
        $variant = $fixture->sku()['variant'];
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $publisher = Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 8, 'provider_location_id' => 'DEFAULT',
            'observed_at' => '2026-09-29T10:00:00+00:00',
        ]);
        $this->app->instance(MarketplaceStoreInventoryPublisher::class, $publisher);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $receipt = app(MarketplaceBaselineFirstCount::class)->commit($actor, (string) $store->id,
            null, $variant->id, 'marketplace', 8, 8, 'baseline-shopee-default');
        $this->assertSame('matched_at_import', $receipt['provider_sync_status']);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id,
            'current_stock' => 8]);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $store->id, 'status' => 'confirmed',
            'target_quantity' => 8, 'attempts' => 0,
        ]);
    }

    private function context(): array
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'BASELINE');
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
            'code' => 'JW-BASELINE-A', 'name' => 'Baseline A', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->where('provider_facility_id', 'WH-A')->update(['inventory_warehouse_id' => $warehouseId]);

        return ['actor' => User::factory()->create(['is_admin' => true, 'is_super_admin' => true]),
            'store_id' => (int) $store->id, 'variant_id' => (int) $variant->id,
            'facility_a' => (int) DB::table('marketplace_store_facilities')
                ->where('marketplace_store_id', $store->id)->where('provider_facility_id', 'WH-A')->value('id')];
    }
}
