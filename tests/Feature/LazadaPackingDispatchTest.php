<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\MarketplacePackingAttempts;
use App\Services\Marketplace\LazadaPackingDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaPackingDispatchTest extends TestCase
{
 use RefreshDatabase;
 public function test_dispatch_revalidates_and_persists_once():void
 {
  config(['marketplace.fulfillment_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $actor=User::factory()->create(['is_admin'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $id=app(MarketplacePackingAttempts::class)->stage($order,$actor->id,'4faf762a-d722-4664-b637-b067b36f296a',['101'],'TFS');
  Http::preventStrayRequests();
  Http::fake(['*/order/items/get*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','status'=>'pending','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0,'semi_managed'=>false]]]),
   '*/order/shipment/providers/get*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['platform_default'=>1,'shipping_allocate_type'=>'TFS']]]),
   '*/order/fulfill/pack'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['pack_order_list'=>[['order_id'=>'100','order_item_list'=>[['order_item_id'=>'101','item_err_code'=>'0','package_id'=>'PK1']]]]]]])]);
  $this->assertSame('succeeded',app(LazadaPackingDispatch::class)->dispatch($id)['status']);
  $this->assertSame('PK1',json_decode(DB::table('marketplace_fulfillment_attempts')->where('id',$id)->value('confirmed_result'),true)['items']['101']['package_id']);
  try { app(LazadaPackingDispatch::class)->dispatch($id); $this->fail('Replayed'); }
  catch (\RuntimeException $error) { $this->assertSame('Packing task was already claimed.',$error->getMessage()); }
  Http::assertSentCount(3);
 }
}
