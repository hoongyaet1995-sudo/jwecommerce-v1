<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StockTakeRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function setupCase(): array
    {
        config(['marketplace.multiwarehouse_test_acceptance_enabled' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'RECOVERY');
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'account_mode' => 'test', 'environment' => 'live', 'external_shop_id' => '300163725140',
            'data_classification' => 'test',
        ]);
        config(['marketplace.lazada.test_store_id' => (string) $store->id]);
        $variant = $fixture->sku()['variant'];
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-TEST', 'name' => 'Test warehouse']]);
        $facility = (int) DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)->value('id');
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'RECOVERY', 'name' => 'Recovery', 'is_active' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_facilities')->where('id', $facility)->update(['inventory_warehouse_id' => $warehouse]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing, 'product_variant_id' => $variant->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $store->id, 'external_order_id' => 'ORDER-82', 'platform_status' => 'packed',
            'platform_updated_at' => 10,
            'encrypted_payload' => Crypt::encryptString(json_encode(['created_at' => now()->subDay()->toIso8601String(),
                'order_items' => [['order_item_id' => 'LINE-82', 'product_id' => '100', 'sku_id' => '200', 'warehouse_code' => 'WH-TEST']]])),
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [(int) $store->id, (int) $variant->id, $facility, $order];
    }

    public function test_supervisor_reviews_one_historical_order_then_existing_facility_count_can_continue(): void
    {
        [$store, $variant, $facility, $order] = $this->setupCase();
        $url = "/admin/api/inventory/store-management/stores/$store/facilities/$facility/skus/$variant/stock-take-recovery";
        $this->getJson($url)->assertUnauthorized();
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonPath('orders.0.id', $order);
        $this->postJson($url.'/historical-review', ['order_ids' => [$order], 'reason' => 'Verified older order outside managed stock'])
            ->assertOk()->assertJsonPath('eligible', true);
        $this->assertDatabaseHas('marketplace_inventory_items', [
            'order_id' => $order, 'line_key' => 'LINE-82', 'status' => 'skipped_historical', 'stock_allocation_id' => null,
        ]);
        $this->assertDatabaseHas('marketplace_stock_take_order_reviews', [
            'order_id' => $order, 'marketplace_store_facility_id' => $facility, 'actor_id' => $actor->id,
        ]);
        $this->assertDatabaseCount('stock_allocations', 0);
        $this->postJson($url.'/historical-review', ['order_ids' => [$order], 'reason' => 'Same review'])
            ->assertOk()->assertJsonPath('eligible', true);
        $this->assertDatabaseCount('marketplace_stock_take_order_reviews', 1);
        $review = app(FacilityStockReview::class)->review($actor, $store, $facility, $variant,
            'count', 12, 'Verified warehouse Stock Take');
        $receipt = app(FacilityStockReview::class)->confirm($actor, $review['id'], 'recovery-count-82');
        $this->assertSame('applied', $receipt['ledger_status']);
        $this->assertSame(12, $receipt['after']['physical']);
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'marketplace_store_id' => $store, 'marketplace_store_facility_id' => $facility,
            'product_variant_id' => $variant, 'target_quantity' => 12,
        ]);
    }

    public function test_active_or_allocated_order_cannot_be_reclassified(): void
    {
        [$store, $variant, $facility, $order] = $this->setupCase();
        $url = "/admin/api/inventory/store-management/stores/$store/facilities/$facility/skus/$variant/stock-take-recovery/order-line-review";
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->postJson($url, ['order_ids' => [$order], 'reason' => 'Review attempt'])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        DB::table('marketplace_inventory_items')->insert([
            'order_id' => $order, 'line_key' => 'LINE-82', 'external_product_id' => '100',
            'external_variant_id' => '200', 'warehouse_code' => 'WH-TEST', 'status' => 'reserved',
            'platform_updated_at' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson($url, ['order_ids' => [$order], 'reason' => 'Review attempt'])->assertUnprocessable();
        $this->assertDatabaseCount('marketplace_stock_take_order_reviews', 0);
    }

    public function test_two_matching_lines_in_one_order_can_be_reviewed_individually(): void
    {
        [$store, $variant, $facility, $order] = $this->setupCase();
        $payload = ['order_items' => [
            ['order_item_id' => 'LINE-82', 'product_id' => '100', 'sku_id' => '200', 'warehouse_code' => 'WH-TEST'],
            ['order_item_id' => 'LINE-83', 'product_id' => '100', 'sku_id' => '200', 'warehouse_code' => 'WH-TEST'],
        ]];
        DB::table('marketplace_orders')->where('id', $order)->update(['encrypted_payload' => Crypt::encryptString(json_encode($payload))]);
        $url = "/admin/api/inventory/store-management/stores/$store/facilities/$facility/skus/$variant/stock-take-recovery";
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'orders');
        $this->postJson($url.'/historical-review', ['lines' => [
            ['order_id' => $order, 'line_key' => 'LINE-82'],
            ['order_id' => $order, 'line_key' => 'LINE-83'],
        ], 'reason' => 'Verified both lines outside managed stock'])
            ->assertOk()->assertJsonPath('eligible', true);
        $this->assertDatabaseCount('marketplace_stock_take_order_reviews', 2);
    }

    public function test_supervisor_can_accept_open_order_stock_risk_with_an_audit_reason(): void
    {
        [$store, $variant, $facility, $order] = $this->setupCase();
        $url = "/admin/api/inventory/store-management/stores/$store/facilities/$facility/skus/$variant/stock-take-recovery/historical-review";
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($actor)->postJson($url, ['lines' => [
            ['order_id' => $order, 'line_key' => 'LINE-82', 'decision' => 'risk_accepted'],
        ], 'reason' => 'Supervisor confirms active order may sell against this counted quantity'])
            ->assertOk()->assertJsonPath('eligible', true);
        $this->assertDatabaseHas('marketplace_stock_take_order_reviews', [
            'order_id' => $order, 'line_key' => 'LINE-82', 'decision' => 'risk_accepted', 'actor_id' => $actor->id,
        ]);
        $this->assertDatabaseCount('stock_allocations', 0);
        $review = app(FacilityStockReview::class)->review($actor, $store, $facility, $variant,
            'count', 7, 'Supervisor-reviewed Stock Take');
        $receipt = app(FacilityStockReview::class)->confirm($actor, $review['id'], 'risk-reviewed-count-82');
        $this->assertSame('applied', $receipt['ledger_status']);
    }
}
