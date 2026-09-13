<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaReadClientTest extends TestCase {
 public function test_seller_warehouses_require_confirmed_result_and_warehouse_address_type():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
  $rows=[['code'=>'dropshipping','status'=>'ACTIVE'],['code'=>'MY-2','status'=>'ACTIVE']];
  Http::preventStrayRequests();
  Http::fake(['*'=>Http::response(['code'=>'0','result'=>['success'=>true,'module'=>$rows]])]);
  $this->assertSame($rows,app(LazadaClient::class)->read('/seller/warehouse/get','fixture-token',['addressTypes'=>'["warehouse"]']));
  Http::assertSent(fn($request)=>$request['addressTypes']==='["warehouse"]');
 }
 public function test_brand_pages_use_official_pagination_parameters():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  Http::preventStrayRequests();
  $page=['module'=>[['brand_id'=>'1','name'=>'No Brand']],'total_record'=>'1'];
  Http::fake(['*'=>Http::response(['code'=>'0','data'=>$page])]);
  $this->assertSame($page,app(LazadaClient::class)->read('/category/brands/query','fixture-token',['startRow'=>'0','pageSize'=>'200']));
  Http::assertSent(fn($request)=>$request['startRow']==='0'&&$request['pageSize']==='200'&&$request->method()==='GET');
 }
 public function test_category_tree_uses_the_official_read_path_without_enabling_creation():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret',
   'marketplace.publish_operations_enabled'=>false]);
  Http::preventStrayRequests();
  $tree=[['category_id'=>1,'name'=>'Category','leaf'=>false,'children'=>[['category_id'=>2,'name'=>'Leaf','leaf'=>true]]]];
  Http::fake(['*'=>Http::response(['code'=>'0','data'=>$tree])]);
  $this->assertSame($tree,app(LazadaClient::class)->read('/category/tree/get','fixture-token'));
  Http::assertSent(fn($request)=>str_contains($request->url(),'/category/tree/get')&&$request->method()==='GET');
  $this->assertFalse(config('marketplace.publish_operations_enabled'));
 }
 public function test_category_attributes_are_available_through_signed_read():void {
  config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
  Http::preventStrayRequests();
  $attributes=[['name'=>'brand','is_mandatory'=>1,'input_type'=>'singleSelect']];
  Http::fake(['*'=>Http::response(['code'=>'0','data'=>$attributes])]);
  $this->assertSame($attributes,app(LazadaClient::class)->read('/category/attributes/get','fixture-token',['primary_category_id'=>'10000664']));
  Http::assertSent(fn($request)=>$request['primary_category_id']==='10000664' && $request->method()==='GET');
 }
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
