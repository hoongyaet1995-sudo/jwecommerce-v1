<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\RebaselineFreeze;
use App\Services\Inventory\StoreManagement\RebaselineZeroPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class InventoryRebaselineProviderZeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.rebaseline_cutover_enabled' => true]);
    }

    public function test_exact_facility_zero_is_confirmed_once_and_never_repeated(): void
    {
        [$epoch, $destination] = $this->frozenDestination();
        $publisher = \Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 6, 'provider_location_id' => 'WH-A',
        ]);
        $publisher->shouldReceive('publish')->once()->withArgs(fn ($store, $listing, $qty, $key) =>
            $store->platform === 'lazada' && $listing->sync_provider_location_id === 'WH-A'
            && $listing->sync_strict_facility === true && $qty === 0
        )->andReturn([
            'requested_quantity' => 0, 'observed_quantity' => 0,
            'provider_location_id' => 'WH-A', 'confirmation_method' => 'provider_readback',
        ]);

        $zero = new RebaselineZeroPublisher($publisher);
        $first = $zero->sendAndVerify($epoch, $destination);
        $second = $zero->sendAndVerify($epoch, $destination);
        $this->assertSame('zero_confirmed', $first['status']);
        $this->assertSame($first, $second);
        $this->assertDatabaseHas('inventory_rebaseline_destinations', [
            'id' => $destination, 'zero_status' => 'zero_confirmed',
        ]);
    }

    public function test_wrong_facility_readback_remains_uncertain_and_never_rewrites(): void
    {
        [$epoch, $destination] = $this->frozenDestination();
        $publisher = \Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 6, 'provider_location_id' => 'WH-A',
        ]);
        $publisher->shouldReceive('publish')->once()->andReturn([
            'requested_quantity' => 0, 'observed_quantity' => 0,
            'provider_location_id' => 'WH-B', 'confirmation_method' => 'provider_readback',
        ]);

        $zero = new RebaselineZeroPublisher($publisher);
        $result = $zero->sendAndVerify($epoch, $destination);
        $this->assertSame('uncertain', $result['status']);
        $this->assertSame('uncertain', $zero->sendAndVerify($epoch, $destination)['status']);
        $this->assertDatabaseMissing('inventory_rebaseline_destinations', [
            'id' => $destination, 'zero_status' => 'zero_confirmed',
        ]);
    }

    public function test_post_write_timeout_is_uncertain_and_cannot_send_a_second_zero(): void
    {
        [$epoch, $destination] = $this->frozenDestination();
        $publisher = \Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 6, 'provider_location_id' => 'WH-A',
        ]);
        $publisher->shouldReceive('publish')->once()->andThrow(new \RuntimeException('timeout'));

        $zero = new RebaselineZeroPublisher($publisher);
        $this->assertSame('uncertain', $zero->sendAndVerify($epoch, $destination)['status']);
        $this->assertSame('uncertain', $zero->sendAndVerify($epoch, $destination)['status']);
    }

    public function test_uncertain_zero_can_be_confirmed_by_read_only_exact_location_check(): void
    {
        [$epoch, $destination] = $this->frozenDestination();
        DB::table('inventory_rebaseline_destinations')->where('id', $destination)
            ->update(['zero_status' => 'uncertain']);
        $publisher = \Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');
        $publisher->shouldReceive('observe')->once()->andReturn([
            'observed_quantity' => 0, 'provider_location_id' => 'WH-A',
        ]);

        $result = (new RebaselineZeroPublisher($publisher))->check($epoch, $destination);
        $this->assertSame('zero_confirmed', $result['status']);
    }

    public function test_changed_topology_blocks_uncertain_zero_reconciliation(): void
    {
        [$epoch, $destination] = $this->frozenDestination();
        DB::table('inventory_rebaseline_destinations')->where('id', $destination)
            ->update(['zero_status' => 'uncertain']);
        $storeId = DB::table('inventory_rebaseline_destinations')->where('id', $destination)->value('store_id');
        app(FacilityRegistry::class)->confirm($storeId, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $publisher = \Mockery::mock(MarketplaceStoreInventoryPublisher::class);
        $publisher->shouldNotReceive('observe');
        $publisher->shouldNotReceive('publish');

        $result = (new RebaselineZeroPublisher($publisher))->check($epoch, $destination);
        $this->assertSame('uncertain', $result['status']);
    }

    private function frozenDestination(): array
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'ZERO');
        $variant = $fixture->sku()['variant'];
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'P-ZERO',
            'external_variant_id' => 'V-ZERO', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(FacilityRegistry::class)->confirm($store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'WH-A', 'name' => 'A', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
            ->update(['inventory_warehouse_id' => $warehouse]);
        $epoch = app(RebaselineFreeze::class)->start($admin, 'zero-'.$store->id);

        return [$epoch['epoch_id'], DB::table('inventory_rebaseline_destinations')->value('id')];
    }
}
