<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\MarketplaceStoreToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaOrderLabelTest extends TestCase
{
 use RefreshDatabase;
 public function test_official_test_label_uses_its_own_application_credentials():void
 {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'production-secret',
   'marketplace.lazada.test_app_key'=>'456','marketplace.lazada.test_app_secret'=>'test-secret']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Official Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'credential_source'=>'official_test','application_id'=>'456',
   'access_token'=>'fixture-token','access_expires_at'=>now()->addHour(),'test_lease_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'packed','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0]]]),
   '*/order/package/document/get*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['doc_type'=>'PDF','pdf_url'=>'https://sellercenter.lazada.com.my/oss/proxy/waybillprintbucket.oss-ap-southeast-1.aliyuncs.com/PDF/abc']]]),
   'https://sellercenter.lazada.com.my/*'=>Http::response("%PDF-1.7\nfixture\n%%EOF")]);
  $bytes=app(\App\Services\Marketplace\LazadaOrderLabel::class)->download($order,'PK1');
  $this->assertStringStartsWith('%PDF-',$bytes);
  Http::assertSent(fn($request)=>str_contains($request->url(),'/order/package/document/get')&&$request['app_key']==='456');
 }
 public function test_admin_receives_pdf_only_for_provider_verified_package():void
 {
  config(['marketplace.read_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'packed','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0]]]),
   '*/order/package/document/get*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['doc_type'=>'PDF','pdf_url'=>'https://sellercenter.lazada.com.my/oss/proxy/waybillprintbucket.oss-ap-southeast-1.aliyuncs.com/PDF/abc']]]),
   'https://sellercenter.lazada.com.my/*'=>Http::response("%PDF-1.7\nfixture\n%%EOF")]);
  $url='/admin/api/marketplace/orders/'.$order.'/label?packageId=';
  $this->getJson($url.'PK1')->assertUnauthorized();
  Http::assertNothingSent();
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get($url.'PK1')->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->getJson($url.'OTHER')->assertStatus(409);
  Http::assertSentCount(4);
 }
}
