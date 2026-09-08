<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaReadClientTest extends TestCase {
 public function test_signed_read_and_authentication_override_rejection():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  Http::preventStrayRequests();
  Http::fake(['api.lazada.com.my/*'=>Http::response(['code'=>'0','data'=>['orders'=>[]]])]);
  $result=app(LazadaClient::class)->read('/orders/get','fixture-token',['limit'=>10]);
  $this->assertSame(['orders'=>[]],$result);
  Http::assertSent(function($request){
   $params=$request->data();$sign=$params['sign'];unset($params['sign']);ksort($params,SORT_STRING);
   $base='/orders/get';foreach($params as $key=>$value)$base.=$key.$value;
   return $request->method()==='GET'&&$params['access_token']==='fixture-token'&&$sign===strtoupper(hash_hmac('sha256',$base,'fixture-secret'));
  });
  foreach([['/product/create',[]],['/orders/get',['access_token'=>'override']]] as [$path,$params]){
   try{app(LazadaClient::class)->read($path,'fixture-token',$params);$this->fail('Unsafe read accepted');}
   catch(\RuntimeException $error){$this->assertNotEmpty($error->getMessage());}
  }
  Http::assertSentCount(1);
 }
 public function test_provider_error_is_not_exposed_as_payload_or_success():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  Http::fake(['*'=>Http::response(['code'=>'InvalidAccessToken','message'=>'sensitive diagnostic'],200)]);
  try{app(LazadaClient::class)->read('/orders/get','fixture-token');$this->fail('Provider failure accepted');}
  catch(\RuntimeException $error){$this->assertStringNotContainsString('sensitive',$error->getMessage());$this->assertStringNotContainsString('fixture-token',$error->getMessage());}
 }
}
