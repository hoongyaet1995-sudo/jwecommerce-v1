<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceInventoryIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function setupStore(): int
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Fixture', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        $product = Product::query()->create(['title'=>'Fixture', 'handle'=>'fixture', 'base_price'=>100, 'status'=>'draft']);
        $variant = $product->variants()->create(['sku'=>'FIXTURE', 'title'=>'One', 'price'=>100, 'stock'=>10, 'enabled'=>true]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store, 'external_product_id'=>'101', 'external_variant_id'=>'201', 'observed_data'=>'{}', 'fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store, 'listing_variant_id'=>$listing, 'product_variant_id'=>$variant->id]);
        return $store;
    }

    private function enable(int $store): void
    {
        config(['marketplace.inventory_accounting_enabled'=>true]);
        DB::table('marketplace_inventory_policies')->insert(['store_id'=>$store, 'enabled'=>true, 'orders_created_after'=>1000, 'reconciled_at'=>now()]);
    }

    private function order(string $status = 'READY_TO_SHIP', int $version = 2000): array
    {
        return ['order_sn'=>'FIXTURE-ORDER', 'order_status'=>$status, 'create_time'=>1500, 'update_time'=>$version,
            'fulfillment_flag'=>'fulfilled_by_local_seller',
            'item_list'=>[['item_id'=>101, 'model_id'=>201, 'model_quantity_purchased'=>3]]];
    }

    public function test_default_read_ingestion_does_not_touch_stock(): void
    {
        $store = $this->setupStore();
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $this->order());
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_enabled_ingestion_reserves_once_then_consumes_without_double_deduction(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $snapshots = app(MarketplaceOrderSnapshotService::class);
        $id = $snapshots->saveShopee($store, $this->order());
        $snapshots->saveShopee($store, $this->order());
        $this->assertEquals(7, DB::table('product_variants')->value('stock'));
        $snapshots->saveShopee($store, $this->order('SHIPPED', 2100));
        $snapshots->saveShopee($store, $this->order('READY_TO_SHIP', 2000));
        $this->assertEquals(7, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'status'=>'consumed', 'platform_updated_at'=>2100]);
        $this->assertDatabaseCount('stock_allocation_events', 2);
    }

    public function test_cancel_uses_frozen_sku_even_if_mapping_is_removed(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $snapshots = app(MarketplaceOrderSnapshotService::class);
        $snapshots->saveShopee($store, $this->order());
        DB::table('marketplace_variant_mappings')->delete();
        $snapshots->saveShopee($store, $this->order('CANCELLED', 2100));
        $snapshots->saveShopee($store, $this->order('CANCELLED', 2100));
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocation_events', 2);
    }

    public function test_historical_order_is_recorded_for_review_without_stock_effects(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $order = $this->order();
        $order['create_time'] = 900;
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'status'=>'exception', 'error_code'=>'historical_order']);
    }

    public function test_unmapped_line_blocks_the_whole_order_but_not_snapshot_ingestion(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $order = $this->order();
        $order['item_list'][] = ['item_id'=>102, 'model_id'=>202, 'model_quantity_purchased'=>1];
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'status'=>'exception', 'error_code'=>'unmapped_order_line']);
        $this->assertDatabaseCount('marketplace_orders', 1);
    }

    public function test_cancellation_before_reservation_cannot_later_reopen_stock_allocation(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $snapshots = app(MarketplaceOrderSnapshotService::class);
        $id = $snapshots->saveShopee($store, $this->order('CANCELLED'));
        $snapshots->saveShopee($store, $this->order('READY_TO_SHIP', 2100));
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'status'=>'exception', 'error_code'=>'inventory_lifecycle_conflict']);
        $snapshots->saveShopee($store, $this->order('READY_TO_SHIP', 2200));
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
    }

    public function test_insufficient_second_line_rolls_back_first_line_and_records_exception(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $variant = DB::table('product_variants')->insertGetId(['product_id'=>1, 'sku'=>'SECOND', 'title'=>'Two', 'price'=>100, 'stock'=>0]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store, 'external_product_id'=>'102', 'external_variant_id'=>'202', 'observed_data'=>'{}', 'fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store, 'listing_variant_id'=>$listing, 'product_variant_id'=>$variant]);
        $order = $this->order();
        $order['item_list'][] = ['item_id'=>102, 'model_id'=>202, 'model_quantity_purchased'=>1];
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
        $this->assertEquals(10, DB::table('product_variants')->where('id', 1)->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
        $this->assertDatabaseCount('stock_allocation_events', 0);
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'error_code'=>'insufficient_stock']);
    }

    public function test_paused_policy_and_missing_reconciliation_do_not_allocate(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        DB::table('marketplace_inventory_policies')->update(['enabled'=>false]);
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $this->order());
        DB::table('marketplace_inventory_policies')->update(['enabled'=>true, 'reconciled_at'=>null]);
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $this->order());
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_changed_order_lines_require_review_after_inventory_was_allocated(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $snapshots = app(MarketplaceOrderSnapshotService::class);
        $id = $snapshots->saveShopee($store, $this->order());
        $variant = DB::table('product_variants')->insertGetId(['product_id'=>1, 'sku'=>'ADDED', 'title'=>'Added', 'price'=>100, 'stock'=>10]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store, 'external_product_id'=>'102', 'external_variant_id'=>'202', 'observed_data'=>'{}', 'fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store, 'listing_variant_id'=>$listing, 'product_variant_id'=>$variant]);
        $order = $this->order('READY_TO_SHIP', 2100);
        $order['item_list'][] = ['item_id'=>102, 'model_id'=>202, 'model_quantity_purchased'=>1];
        $snapshots->saveShopee($store, $order);
        $this->assertEquals(10, DB::table('product_variants')->where('id', $variant)->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 1);
        $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'error_code'=>'inventory_lifecycle_conflict']);
    }

    public function test_exception_query_is_protected_paginated_filtered_and_excludes_private_payload(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $order = $this->order();
        $order['create_time'] = 900;
        $order['recipient_address'] = ['name'=>'SECRET RECIPIENT', 'phone'=>'PRIVATE PHONE'];
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
        $order['order_sn'] = 'OTHER-ORDER';
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
        $url = '/admin/api/marketplace/inventory/exceptions';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>false]));
        // Existing customer/web-session separation logs non-admin web users out.
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>true]);
        $response = $this->getJson($url.'?per_page=1&storeId='.$store)->assertOk()
            ->assertJsonPath('total', 2)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error_code', 'historical_order');
        $this->assertStringNotContainsString('SECRET RECIPIENT', $response->getContent());
        $this->assertStringNotContainsString('encrypted_payload', $response->getContent());
        $this->getJson($url.'?q=FIXTURE-ORDER')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($url.'?storeId=999')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?error=insufficient_stock')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?per_page=101')->assertUnprocessable();
        $this->getJson($url.'?error=unknown')->assertUnprocessable();
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_inventory_read_preserves_multiple_stores_and_does_not_invent_physical_counts(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $this->order());
        $second = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Second store', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'disconnected']);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$second, 'external_product_id'=>'301', 'external_variant_id'=>'401', 'observed_data'=>'{}', 'fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$second, 'listing_variant_id'=>$listing, 'product_variant_id'=>1]);
        $url = '/admin/api/marketplace/inventory';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>true]);
        $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.available_stock', 7)->assertJsonPath('data.0.marketplace_reserved_stock', 3)
            ->assertJsonPath('data.0.physical_stock', null)->assertJsonPath('data.0.safety_stock', null)
            ->assertJsonPath('data.0.reconciliation_required', true)->assertJsonCount(2, 'data.0.channel_links')
            ->assertJsonPath('data.0.channel_links.1.connection_status', 'disconnected')
            ->assertJsonPath('data.0.channel_links.0.stock_push_enabled', false);
        $this->getJson($url.'?storeId='.$second)->assertOk()->assertJsonPath('total', 1);
        $this->getJson($url.'?storeId=999')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?q=not-found')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'?q=FIXTURE')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($url.'?per_page=101')->assertUnprocessable();
        $this->assertEquals(7, DB::table('product_variants')->value('stock'));
    }

    public function test_inventory_catalog_over_three_hundred_skus_remains_paginated(): void
    {
        $this->setupStore();
        $rows = [];
        for ($i=0; $i<304; $i++) $rows[] = ['product_id'=>1, 'sku'=>'BULK-'.$i, 'title'=>'Bulk', 'price'=>100, 'stock'=>0];
        DB::table('product_variants')->insert($rows);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>true]);
        $this->getJson('/admin/api/marketplace/inventory?per_page=100&page=4')->assertOk()
            ->assertJsonPath('total', 305)->assertJsonPath('last_page', 4)->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.id', 301)->assertJsonCount(0, 'data.0.channel_links');
    }

    public function test_nonlocal_or_unsupported_fulfillment_never_allocates_jw_warehouse_stock(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        $examples = [
            ['fulfillment_flag'=>'fulfilled_by_shopee'],
            ['fulfillment_flag'=>null],
            ['advance_package'=>true],
        ];
        foreach ($examples as $index=>$change) {
            $order = array_replace($this->order(), $change);
            $order['order_sn'] = 'FULFILLMENT-'.$index;
            $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
            $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'error_code'=>'unsupported_fulfillment']);
        }
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
    }

    public function test_bundle_and_partial_quantity_changes_are_explicit_inventory_exceptions(): void
    {
        $store = $this->setupStore();
        $this->enable($store);
        foreach ([['is_fulfillment_mapping'=>true], ['components'=>[['quantity'=>2]]], ['cancelled_qty'=>1], ['returned_qty'=>1], ['active_qty'=>2]] as $index=>$change) {
            $order = $this->order();
            $order['order_sn'] = 'QUANTITY-'.$index;
            $order['item_list'][0] += $change;
            $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, $order);
            $this->assertDatabaseHas('marketplace_order_inventory', ['order_id'=>$id, 'error_code'=>'unsupported_order_composition']);
        }
        $this->assertEquals(10, DB::table('product_variants')->value('stock'));
        $this->assertDatabaseCount('stock_allocations', 0);
    }
}
