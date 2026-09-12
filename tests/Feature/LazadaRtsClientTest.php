<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaRtsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaRtsClientTest extends TestCase
{
 public function test_gate_and_signed_single_request():void
 {
  Http::preventStrayRequests();
  config(['marketplace.fulfillment_operations_enabled'=>false]);
  try { app(LazadaRtsClient::class)->send('fixture-token',['PK1']);$this->fail('Gate bypassed'); }
  catch (\RuntimeException $e) { $this->assertSame('Marketplace fulfillment is disabled.',$e->getMessage()); }
  Http::assertNothingSent();
  config(['marketplace.fulfillment_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  Http::fake(['*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['packages'=>[['package_id'=>'PK1','item_err_code'=>'0']]]]])]);
  $this->assertTrue(app(LazadaRtsClient::class)->send('fixture-token',['PK1'])['complete']);
  Http::assertSent(function($request){
   $data=$request->data();$sign=$data['sign'];unset($data['sign']);ksort($data,SORT_STRING);$base='/order/package/rts';
   foreach($data as $key=>$value)$base.=$key.$value;
   return $request->method()==='POST'&&json_decode($data['readyToShipReq'],true)===['packages'=>[['package_id'=>'PK1']]]
    &&$sign===strtoupper(hash_hmac('sha256',$base,'fixture-secret'));
  });
  Http::assertSentCount(1);
 }
}
