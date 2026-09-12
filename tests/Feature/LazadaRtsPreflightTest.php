<?php
namespace Tests\Feature;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaRtsPreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaRtsPreflightTest extends TestCase
{
 use RefreshDatabase;
 public function test_cancelled_sibling_blocks_entire_package():void
 {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'live','status'=>'connected']);
  MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'packed','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $line=['order_id'=>'100','order_item_id'=>'101','package_id'=>'PK1','status'=>'packed','is_fbl'=>0,'is_digital'=>0,'delivery_option_sof'=>0,'semi_managed'=>false];
  Http::preventStrayRequests();
  Http::fake(['*'=>Http::sequence()->push(['code'=>'0','data'=>[$line]])->push(['code'=>'0','data'=>[$line,array_replace($line,['order_item_id'=>'102','status'=>'canceled'])]])]);
  $this->assertSame(['PK1'=>['101']],app(LazadaRtsPreflight::class)->inspect($order,['PK1'])['packages']);
  $this->expectException(\RuntimeException::class);
  $this->expectExceptionMessage('Package is no longer eligible for RTS.');
  app(LazadaRtsPreflight::class)->inspect($order,['PK1']);
 }
}
