<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceManualRefreshInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_books_exact_lazada_facility_and_repeated_refresh_skips_it(): void
    {
        config(['marketplace.read_operations_enabled' => true, 'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '123', 'marketplace.lazada.app_secret' => 'fixture-secret']);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'REFRESH-EXACT');
        DB::table('marketplace_stores')->where('id', $store->id)->update(['environment' => 'live']);
        $variant = $fixture->sku()['variant'];
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        MarketplaceStoreToken::create(['store_id' => $store->id, 'application_id' => '123',
            'access_token' => 'fixture-token', 'access_expires_at' => now()->addHour()]);
        app(FacilityRegistry::class)->confirm((int) $store->id,
            [['provider_id' => 'WH-EXACT', 'name' => 'Exact']]);
        $facility = DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)->first();
        $warehouse = DB::table('inventory_warehouses')->insertGetId(['code' => 'JW-REFRESH-EXACT',
            'name' => 'Exact', 'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_facilities')->where('id', $facility->id)->update(['inventory_warehouse_id' => $warehouse]);
        $oldPool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'store:marketplace:'.$store->id,
            'kind' => 'store', 'marketplace_store_id' => $store->id, 'created_at' => now(), 'updated_at' => now()]);
        $newPool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'facility:'.$warehouse,
            'kind' => 'facility', 'warehouse_id' => $warehouse, 'created_at' => now(), 'updated_at' => now()]);
        $oldBalance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $oldPool,
            'product_variant_id' => $variant->id, 'physical_stock' => 20, 'reserved_stock' => 0,
            'safety_stock' => 0, 'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $newBalance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $newPool,
            'product_variant_id' => $variant->id, 'physical_stock' => 3, 'reserved_stock' => 0,
            'safety_stock' => 0, 'counted_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_order_activations')->insert(['channel_key' => 'marketplace:'.$store->id,
            'product_variant_id' => $variant->id, 'inventory_balance_id' => $oldBalance,
            'actor_id' => $actor->id, 'reason' => 'Legacy count',
            'orders_created_after' => now()->subDay(), 'created_at' => now()->subDay()]);
        DB::table('inventory_fulfillment_routes')->insert(['channel_key' => 'marketplace:'.$store->id,
            'warehouse_id' => $warehouse, 'platform_location_id' => 'WH-EXACT', 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id,
            'external_product_id' => '100', 'external_variant_id' => '200', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store->id,
            'listing_variant_id' => $listing, 'product_variant_id' => $variant->id,
            'created_at' => now(), 'updated_at' => now()]);
        $order = DB::table('marketplace_orders')->insertGetId(['store_id' => $store->id,
            'external_order_id' => '100', 'platform_status' => 'pending', 'platform_updated_at' => 100,
            'encrypted_payload' => Crypt::encryptString('{"order_items":[]}'), 'fetched_at' => now()]);
        $created = now()->subHour()->format('Y-m-d H:i:s O');
        $updated = now()->format('Y-m-d H:i:s O');
        Http::preventStrayRequests();
        Http::fake(['*/order/get*' => Http::response(['code' => '0', 'data' => [
            'order_id' => '100', 'statuses' => ['pending'], 'created_at' => $created, 'updated_at' => $updated,
        ]]), '*/order/items/get*' => Http::response(['code' => '0', 'data' => [[
            'order_id' => '100', 'order_item_id' => '101', 'product_id' => '100', 'sku_id' => '200',
            'status' => 'pending', 'is_fbl' => 0, 'semi_managed' => false,
            'warehouse_code' => 'WH-EXACT', 'updated_at' => $updated,
        ]]])]);

        $url = '/admin/api/marketplace/orders/'.$order.'/refresh';
        $first = $this->actingAs($actor)->postJson($url);
        $first->assertOk()->assertJsonPath('inventory_audit.0.result', 'corrected');
        $this->assertDatabaseHas('inventory_balances', ['id' => $oldBalance, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $newBalance, 'physical_stock' => 3, 'reserved_stock' => 1]);
        $this->postJson($url)->assertOk()->assertJsonPath('inventory_audit.0.result', 'already_correct');
        $this->assertDatabaseCount('stock_allocations', 1);
        $this->assertDatabaseCount('marketplace_order_inventory_corrections', 1);
    }
}
