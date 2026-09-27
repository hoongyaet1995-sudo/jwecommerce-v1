<?php

namespace Tests\Feature;

use App\Contracts\StoreInventoryPublisher;
use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use App\Services\Inventory\StoreManagement\FacilitySyncRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilitySyncRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_location_readback_confirms_only_matching_facility_task(): void
    {
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'FACILITY-RECOVERY');
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'JW-RECOVERY-A', 'name' => 'JW Recovery A',
            'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'provider_location_id' => 'WH-A', 'observed_quantity' => 6,
            'observed_at' => now()->toIso8601String(),
        ]);
        $preCountReference = app(FacilitySyncRecovery::class)->check(
            $store->id, $facility->id, $variant->id, $publisher);
        $this->assertSame(6, $preCountReference['marketplace_reported_stock']);
        $this->assertNull($preCountReference['jw_available_stock']);
        $this->assertNull($preCountReference['difference']);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
        $review = app(FacilityStockReview::class)->review($actor, (int) $store->id,
            (int) $facility->id, (int) $variant->id, 'count', 10, 'Physical count');
        $receipt = app(FacilityStockReview::class)->confirm($actor, $review['id'], 'recovery-count');
        $taskId = $receipt['provider_sync_task']['id'];
        $service = app(FacilitySyncRecovery::class);
        $this->assertSame('awaiting_confirmation', $service->details($store->id, $facility->id, $variant->id)['status']);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->withArgs(function ($actualStore, $actualListing) use ($store): bool {
            $this->assertSame((int) $store->id, (int) $actualStore->id);
            $this->assertSame('WH-A', $actualListing->sync_provider_location_id);
            $this->assertTrue($actualListing->sync_strict_facility);

            return true;
        })->andReturn(['provider_location_id' => 'WH-A', 'observed_quantity' => 10,
            'observed_at' => now()->toIso8601String()]);
        $result = $service->check($store->id, $facility->id, $variant->id, $publisher);
        $this->assertSame('synced', $result['status']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $taskId, 'status' => 'confirmed']);

        DB::table('inventory_store_sync_tasks')->where('id', $taskId)->update(['status' => 'uncertain']);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'provider_location_id' => 'WH-A', 'observed_quantity' => 6,
            'observed_at' => now()->toIso8601String(),
        ]);
        $mismatch = $service->check($store->id, $facility->id, $variant->id, $publisher);
        $this->assertSame('sync_failed', $mismatch['status']);
        $this->assertSame(4, $mismatch['difference']);
        $this->assertTrue($mismatch['can_resync']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $taskId, 'status' => 'failed']);

        DB::table('marketplace_store_tokens')->insert([
            'store_id' => $store->id, 'application_id' => 'test', 'credential_source' => 'official_test',
            'access_token' => 'test', 'test_lease_expires_at' => now()->subMinute(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $expired = $service->details($store->id, $facility->id, $variant->id);
        $this->assertSame('auth_expired', $expired['status']);
        $this->assertFalse($expired['can_check']);
        $this->assertSame(6, $expired['marketplace_reported_stock']);
        DB::table('marketplace_store_tokens')->where('store_id', $store->id)->delete();

        $balanceId = DB::table('inventory_store_sync_tasks')->where('id', $taskId)->value('inventory_balance_id');
        DB::table('inventory_balances')->where('id', $balanceId)->update([
            'physical_stock' => null, 'counted_at' => null, 'version' => 2,
        ]);
        $this->assertNull($service->details($store->id, $facility->id, $variant->id)['marketplace_reported_stock']);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'provider_location_id' => 'WH-A', 'observed_quantity' => 6,
            'observed_at' => now()->toIso8601String(),
        ]);
        $staleResult = $service->check($store->id, $facility->id, $variant->id, $publisher);
        $this->assertSame(6, $staleResult['marketplace_reported_stock']);
        $this->assertSame('not_synced', $staleResult['status']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $taskId, 'status' => 'superseded']);

        Queue::fake();
        config(['marketplace.multiwarehouse_stock_sync_enabled' => true]);
        $this->assertFalse($service->details($store->id, $facility->id, $variant->id)['can_resync']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->resync($store->id, $facility->id, $variant->id, 'resync-a');
    }
}
