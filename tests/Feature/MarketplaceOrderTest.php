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

    public function test_order_pull_reads_details_and_saves_without_creating_self_store_orders(): void
    {
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
}
