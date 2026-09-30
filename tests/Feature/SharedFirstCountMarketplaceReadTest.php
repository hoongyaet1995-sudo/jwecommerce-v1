<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\MarketplaceItemStockSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class SharedFirstCountMarketplaceReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_lazada_store_row_reads_only_enabled_warehouses(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'READ');
        DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id,
            'marketplace_store_id' => $store->id, 'management_status' => 'managed',
            'shared_pool_takeover_enabled' => true, 'version' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id,
            'external_product_id' => '123', 'external_variant_id' => '456',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing,
            'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_facility_scans')->insert(['marketplace_store_id' => $store->id,
            'status' => 'verified', 'topology' => 'multi_warehouse', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2, 3] as $number) {
            DB::table('marketplace_store_facilities')->insert(['marketplace_store_id' => $store->id,
                'provider_facility_id' => 'WH-'.$number, 'name' => 'Warehouse '.$number,
                'is_active' => true, 'shared_pool_takeover_enabled' => $number !== 3,
                'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $snapshot = \Mockery::mock(MarketplaceItemStockSnapshot::class);
        $snapshot->shouldReceive('read')->once()->andReturn(['stock' => ['456' => ['WH-1' => 7, 'WH-2' => 11, 'WH-3' => 90]], 'observed_at' => now()->toIso8601String()]);
        $snapshot->shouldReceive('notApplicable')->twice()->andReturn(false);
        $snapshot->shouldReceive('quantity')->twice()->andReturn(7, 11);
        $this->app->instance(MarketplaceItemStockSnapshot::class, $snapshot);

        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($actor)->postJson('/admin/api/inventory/store-management/shared-skus/'.$sku['variant']->id.'/marketplace-stock')
            ->assertOk()->assertJsonCount(1, 'stores')->assertJsonPath('available_total', 18)
            ->assertJsonPath('stores.0.quantity', 18)->assertJsonCount(2, 'stores.0.warehouses')
            ->assertJsonPath('stores.0.warehouses.0.name', 'Warehouse 1');
    }
}
