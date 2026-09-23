<?php
namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceSingleOrderRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopee_refresh_reads_only_requested_order(): void
    {
        config(['marketplace.read_operations_enabled'=>true,'marketplace.shopee.enabled'=>true,
            'marketplace.shopee.environment'=>'sandbox','marketplace.shopee.partner_id'=>'123',
            'marketplace.shopee.partner_key'=>'secret','marketplace.shopee.callback_url'=>'https://example.test/callback',
            'marketplace.shopee.applications.sandbox'=>['partner_id'=>'123','partner_key'=>'secret','callback_url'=>'https://example.test/callback']]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','external_shop_id'=>'456',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token',
            'refresh_token'=>'fixture-refresh','access_expires_at'=>now()->addHour()]);
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'ORDER1',
            'platform_status'=>'UNPAID','platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString('{"item_list":[]}'),'fetched_at'=>now()]);
        Http::preventStrayRequests();
        Http::fake(['*/get_order_detail*'=>Http::response(['response'=>['order_list'=>[[
            'order_sn'=>'ORDER1','order_status'=>'READY_TO_SHIP','update_time'=>200,'shipping_carrier'=>'SPX','item_list'=>[]]]]])]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]))
            ->postJson('/admin/api/marketplace/orders/'.$order.'/refresh')->assertOk()->assertJsonPath('data.platform_status','READY_TO_SHIP');
        $this->assertSame('READY_TO_SHIP',DB::table('marketplace_orders')->find($order)->platform_status);
        Http::assertSentCount(1);
    }

    public function test_lazada_refresh_updates_only_requested_order_from_provider(): void
    {
        config(['marketplace.read_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected','external_shop_id'=>'300']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending',
            'platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString('{"order_items":[]}'),'fetched_at'=>now()]);
        Http::preventStrayRequests();
        Http::fake(['*/order/get*'=>Http::response(['code'=>'0','data'=>['order_id'=>'100','statuses'=>['packed'],'updated_at'=>'2026-09-23 20:00:00 +0800']]),
            '*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','status'=>'packed','package_id'=>'PK1','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0]]])]);
        $url='/admin/api/marketplace/orders/'.$order.'/refresh';
        $this->postJson($url)->assertUnauthorized();
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson($url)->assertOk()->assertJsonPath('data.id',$order);
        $this->assertSame('packed',DB::table('marketplace_orders')->find($order)->platform_status);
        Http::assertSentCount(2);
    }

    public function test_unsupported_order_refresh_keeps_existing_snapshot(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopify','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'OLD',
            'platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>Crypt::encryptString('{}'),'fetched_at'=>now()]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))
            ->postJson('/admin/api/marketplace/orders/'.$order.'/refresh')->assertStatus(409);
        $this->assertSame('pending',DB::table('marketplace_orders')->find($order)->platform_status);
        Http::assertNothingSent();
    }
}
