<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Marketplace\CurrentStockMarketplaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\StoreInventoryFixture;

class InventoryModeResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_mode_switch_requires_explicit_reset_confirmation_and_restarts_every_sku(): void
    {
        config(['inventory.current_stock_only' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $product = Product::create(['title' => 'Reset sample', 'handle' => 'reset-sample', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'RESET-SKU', 'title' => 'Default', 'price' => 1, 'stock' => 18]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'store:website', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now()]);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'physical_stock' => 18, 'reserved_stock' => 2,
            'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentStockLedger::class)->setAbsolute($balance, 18, 'reset:old-count', 'Old First Count');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $version = (int) DB::table('inventory_configuration')->where('id', 1)->value('version');
        $url = '/admin/api/inventory/store-management/mode';
        $this->actingAs($admin)->postJson($url, ['target' => 'shared_pool', 'configuration_version' => $version,
            'distribution' => []])->assertUnprocessable();
        $this->assertSame(18, (int) DB::table('inventory_balances')->where('id', $balance)->value('current_stock'));

        $this->postJson($url, ['target' => 'shared_pool', 'configuration_version' => $version,
            'distribution' => [], 'confirm_reset' => true])->assertOk()->assertJsonPath('operating_mode', 'shared_pool');

        $this->assertDatabaseHas('inventory_balances', ['id' => $balance, 'current_stock' => null, 'counted_at' => null]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 0]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id,
            'first_physical_verified_at' => null, 'first_physical_verification_operation' => null]);
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
        $this->assertDatabaseHas('inventory_mode_resets', ['actor_id' => $admin->id,
            'source_mode' => 'independent_stores', 'target_mode' => 'shared_pool']);
        $this->assertDatabaseHas('inventory_configuration', ['id' => 1, 'operating_mode' => 'shared_pool']);
        $this->assertNotNull(DB::table('inventory_configuration')->where('id', 1)->value('mode_reset_at'));
    }

    public function test_non_super_admin_cannot_reset_inventory(): void
    {
        config(['inventory.current_stock_only' => true]);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $version = (int) DB::table('inventory_configuration')->where('id', 1)->value('version');
        $this->actingAs($staff)->postJson('/admin/api/inventory/store-management/mode', [
            'target' => 'independent_stores', 'configuration_version' => $version,
            'distribution' => [], 'confirm_reset' => true,
        ])->assertForbidden();
    }

    public function test_first_count_review_reopens_shared_pool_after_reset(): void
    {
        config(['inventory.current_stock_only' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $product = Product::create(['title' => 'Restart count', 'handle' => 'restart-count', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'RESTART-COUNT', 'title' => 'Default', 'price' => 1, 'stock' => 9]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $version = (int) DB::table('inventory_configuration')->where('id', 1)->value('version');
        $this->actingAs($admin)->postJson('/admin/api/inventory/store-management/mode', [
            'target' => 'shared_pool', 'configuration_version' => $version,
            'distribution' => [], 'confirm_reset' => true,
        ])->assertOk();

        $review = $this->postJson('/admin/api/inventory/store-management/reviews', [
            'kind' => 'count', 'variant_id' => $variant->id, 'channel_key' => 'website',
            'physical_count' => 7, 'reason' => 'New First Count after mode reset',
        ])->assertCreated()->json();
        $this->postJson('/admin/api/inventory/store-management/reviews/'.$review['id'].'/confirm', [
            'operation_key' => 'restart-count-review-1',
        ])->assertOk();

        $pool = DB::table('inventory_pools')->where('pool_key', 'shared')->first();
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_pool_id' => $pool->id, 'product_variant_id' => $variant->id, 'current_stock' => 7,
        ]);
        $this->assertSame(7, (int) DB::table('product_variants')->where('id', $variant->id)->value('stock'));
    }

    public function test_marketplace_order_waits_for_first_count_and_never_replays_pre_count_order(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update([
            'operating_mode' => 'shared_pool', 'mode_reset_at' => now()->subMinutes(2),
        ]);
        $fixture = new StoreInventoryFixture;
        $variant = $fixture->sku()['variant'];
        $store = $fixture->store('shopee', 'mode-reset');
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store->id,
            'listing_variant_id' => $listing, 'product_variant_id' => $variant->id,
            'created_at' => now(), 'updated_at' => now()]);
        $oldTime = now()->subHour()->timestamp;
        $payload = ['create_time' => $oldTime, 'fulfillment_flag' => 'fulfilled_by_local_seller',
            'item_list' => [['item_id' => 100, 'model_id' => 200, 'model_quantity_purchased' => 2]]];
        $order = (object) ['id' => 123, 'external_order_id' => 'OLD-ORDER',
            'platform_status' => 'READY_TO_SHIP', 'platform_updated_at' => 1];
        $service = app(CurrentStockMarketplaceOrder::class);

        $this->assertSame('skipped_not_counted', $service->apply($store, $order, $payload));
        $this->assertDatabaseCount('inventory_current_stock_order_lines', 0);
        $pool = DB::table('inventory_pools')->where('pool_key', 'shared')->first();
        $balance = DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
            ->where('product_variant_id', $variant->id)->value('id');
        app(CurrentStockLedger::class)->setAbsolute($balance, 10, 'reset:new-count', 'New First Count');
        $this->assertSame('skipped_before_first_count', $service->apply($store, $order, $payload));
        $this->assertSame(10, (int) DB::table('inventory_balances')->where('id', $balance)->value('current_stock'));

        $newOrder = (object) ['id' => 124, 'external_order_id' => 'NEW-ORDER',
            'platform_status' => 'READY_TO_SHIP', 'platform_updated_at' => 2];
        $payload['create_time'] = now()->addMinutes(1)->timestamp;
        $this->assertSame('reserved', $service->apply($store, $newOrder, $payload));
        $this->assertSame(8, (int) DB::table('inventory_balances')->where('id', $balance)->value('current_stock'));
    }
}
