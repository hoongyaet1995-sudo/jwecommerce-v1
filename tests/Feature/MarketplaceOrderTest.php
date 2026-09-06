<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceOrderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.operations_enabled'=>true]);
    }

    public function test_failed_order_run_retry_preserves_cursor_and_rejects_duplicate_or_disconnected_retry(): void
    {
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        \Illuminate\Support\Facades\Queue::fake();
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $run = DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store, 'status'=>'failed',
            'time_from'=>100, 'time_to'=>300, 'next_cursor'=>'page-two', 'pages_completed'=>1, 'orders_processed'=>50,
            'error_code'=>'order_pull_failed', 'finished_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
        $url = '/admin/api/marketplace/order-runs/'.$run.'/retry';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->postJson($url)->assertStatus(202)->assertJsonPath('runId', $run);
        $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$run, 'status'=>'queued', 'next_cursor'=>'page-two',
            'time_from'=>100, 'time_to'=>300, 'pages_completed'=>1, 'orders_processed'=>50, 'error_code'=>null, 'finished_at'=>null]);
        $this->postJson($url)->assertConflict();
        DB::table('marketplace_order_runs')->where('id', $run)->update(['status'=>'failed']);
        DB::table('marketplace_stores')->where('id', $store)->update(['status'=>'disconnected']);
        $this->postJson($url)->assertConflict();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullShopeeOrders::class, 1);
        $this->assertDatabaseCount('marketplace_order_runs', 1);
    }

    public function test_duplicate_and_older_order_snapshots_do_not_duplicate_or_regress(): void
    {
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $service = app(MarketplaceOrderSnapshotService::class);
        $order = ['order_sn'=>'ORDER1', 'update_time'=>200, 'order_status'=>'SHIPPED', 'currency'=>'MYR',
            'total_amount'=>100, 'recipient_address'=>['name'=>'PRIVATE CUSTOMER'], 'item_list'=>[]];
        $service->saveShopee($id, $order);
        $service->saveShopee($id, $order);
        $service->saveShopee($id, array_replace($order, ['update_time'=>100, 'order_status'=>'READY_TO_SHIP']));
        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseHas('marketplace_orders', ['store_id'=>$id, 'external_order_id'=>'ORDER1', 'platform_status'=>'SHIPPED']);
        $this->assertStringNotContainsString('PRIVATE CUSTOMER', DB::table('marketplace_orders')->value('encrypted_payload'));
        $this->assertDatabaseCount('orders', 0);
        $this->getJson('/admin/api/marketplace/orders')->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $response = $this->getJson('/admin/api/marketplace/orders?storeId='.$id)->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.external_order_id', 'ORDER1');
        $this->assertArrayNotHasKey('encrypted_payload', $response->json('data.0'));
        $this->assertStringNotContainsString('PRIVATE CUSTOMER', $response->getContent());
    }

    public function test_order_detail_resolves_only_same_store_mapping_and_does_not_expose_raw_payload(): void
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Sandbox',
            'external_shop_id'=>'227482706', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $other = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Other',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        $product = \App\Models\Product::create(['title'=>'PS5', 'handle'=>'detail-ps5', 'base_price'=>1400, 'status'=>'active']);
        $variant = $product->variants()->create(['sku'=>'JW-PS5', 'title'=>'White', 'price'=>1400, 'stock'=>7, 'enabled'=>true]);
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, [
            'order_sn'=>'TEST-ORDER', 'order_status'=>'READY_TO_SHIP', 'update_time'=>200,
            'currency'=>'MYR', 'total_amount'=>1407.31, 'recipient_address'=>['name'=>'PRIVATE'], 'access_token'=>'NEVER-RETURN',
            'item_list'=>[['item_id'=>846055463, 'model_id'=>12983425, 'item_name'=>'PS5', 'model_name'=>'White,500GB',
                'model_sku'=>'JWSTORE1PS5-500GB', 'model_quantity_purchased'=>1]],
        ]);
        $url = '/admin/api/marketplace/orders/'.$id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        foreach ([$other, $store] as $listingStore) {
            $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$listingStore,
                'external_product_id'=>'846055463', 'external_variant_id'=>'12983425', 'observed_data'=>'{}',
                'created_at'=>now(), 'updated_at'=>now(), 'fetched_at'=>now()]);
            $this->putJson('/admin/api/marketplace/listings/'.$listing.'/mapping', ['productId'=>$product->id, 'variantId'=>$variant->id])->assertOk();
            $response = $this->getJson($url)->assertOk()->assertJsonPath('data.external_order_id', 'TEST-ORDER')
                ->assertJsonPath('data.lines.0.quantity', 1)
                ->assertJsonPath('data.lines.0.mapping_status', $listingStore === $store ? 'mapped' : 'unmapped');
            $this->assertStringNotContainsString('NEVER-RETURN', $response->getContent());
            $this->assertStringNotContainsString('PRIVATE', $response->getContent());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $response->assertJsonPath('data.lines.0.jw_sku', 'JW-PS5');
        $this->assertEquals(7, $variant->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->getJson('/admin/api/marketplace/orders/999999')->assertNotFound();
        config(['marketplace.operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
    }

    public function test_order_pull_reads_details_and_saves_without_creating_self_store_orders(): void
    {
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>true]);
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected',
            'created_at'=>now(), 'updated_at'=>now()]);
        \App\Models\MarketplaceStoreToken::create(['store_id'=>$id, 'application_id'=>'123', 'access_token'=>'access', 'refresh_token'=>'refresh', 'access_expires_at'=>now()->addHours(4)]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            '*/get_order_list*'=>\Illuminate\Support\Facades\Http::response(['response'=>['order_list'=>[['order_sn'=>'ORDER1']], 'more'=>false]]),
            '*/get_order_detail*'=>\Illuminate\Support\Facades\Http::response(['response'=>['order_list'=>[['order_sn'=>'ORDER1', 'order_status'=>'READY_TO_SHIP', 'update_time'=>200]]]]),
        ]);
        $service = app(\App\Services\Marketplace\ShopeeOrderService::class);
        $this->assertFalse($service->pullPage($id, 100, 300)['has_more']);
        $service->pullPage($id, 100, 300);
        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('orders', 0);
        $run = DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$id, 'status'=>'queued',
            'time_from'=>100, 'time_to'=>300, 'created_at'=>now(), 'updated_at'=>now()]);
        (new \App\Jobs\PullShopeeOrders($run))->handle($service);
        $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$run, 'status'=>'succeeded', 'orders_processed'=>1]);
        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        \Illuminate\Support\Facades\Queue::fake();
        $task = $this->postJson('/admin/api/marketplace/stores/'.$id.'/orders-fetch', ['from'=>100, 'to'=>300])->assertStatus(202)->json('runId');
        $this->postJson('/admin/api/marketplace/stores/'.$id.'/orders-fetch', ['from'=>100, 'to'=>300])->assertStatus(202)->assertJsonPath('runId', $task);
        $this->getJson('/admin/api/marketplace/order-runs/'.$task)->assertOk()->assertJsonPath('status', 'queued');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullShopeeOrders::class, 1);
    }

    public function test_recipient_is_opt_in_allowlisted_and_packages_do_not_claim_fulfillment(): void
    {
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Test',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        $id = app(MarketplaceOrderSnapshotService::class)->saveShopee($store, [
            'order_sn'=>'RECIPIENT-TEST', 'order_status'=>'READY_TO_SHIP', 'update_time'=>200, 'item_list'=>[],
            'recipient_address'=>['name'=>'Test Recipient', 'phone'=>'MASKED', 'full_address'=>'Test Street',
                'region'=>'MY', 'unapproved_field'=>'HIDDEN-VALUE'],
            'shipping_carrier'=>'Platform Carrier',
            'package_list'=>[['package_number'=>'PKG1', 'logistics_status'=>'LOGISTICS_READY', 'shipping_carrier'=>'Platform Carrier', 'unapproved_field'=>'HIDDEN-VALUE']],
        ]);
        $url = '/admin/api/marketplace/orders/'.$id;
        $this->getJson($url.'?includeRecipient=1')->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->getJson($url)->assertOk()->assertJsonMissingPath('data.recipient');
        $response = $this->getJson($url.'?includeRecipient=1')->assertOk()
            ->assertJsonPath('data.recipient.name', 'Test Recipient')
            ->assertJsonPath('data.recipient.phone', 'MASKED')
            ->assertJsonPath('data.recipient.city', null)
            ->assertJsonPath('data.packages.0.package_number', 'PKG1')
            ->assertJsonPath('data.packages.0.logistics_status', 'LOGISTICS_READY')
            ->assertJsonPath('data.capabilities.fulfillment', false);
        $this->assertStringNotContainsString('HIDDEN-VALUE', $response->getContent());
        $this->assertStringNotContainsString('Test Recipient', $this->getJson('/admin/api/marketplace/orders')->getContent());
        $this->getJson($url.'?includeRecipient=invalid')->assertUnprocessable();
        DB::table('marketplace_stores')->where('id', $store)->update(['status'=>'disconnected']);
        $this->getJson($url.'?includeRecipient=1')->assertOk()->assertJsonPath('data.capabilities.fulfillment', false);
    }
}
