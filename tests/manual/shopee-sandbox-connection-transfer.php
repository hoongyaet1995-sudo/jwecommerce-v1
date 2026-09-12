<?php
// Run only through the paired Python pipe. Credential output is never a terminal artifact.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$stage='entry';
try {
 if(!getenv('JW_SANDBOX_PIPE'))throw new RuntimeException('Private pipe required.');
 $db=Illuminate\Support\Facades\DB::class;
 if(in_array('--import',$argv??[],true)){
  if(!$app->environment('local'))throw new RuntimeException('Local sandbox required.');
  $stage='decode';$data=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
  $stage='identity';
  if(($data['shop_id']??null)!=='227482706'||($data['environment']??null)!=='sandbox'||!ctype_digit($data['application_id']??'')
    ||!preg_match('/\A[A-Za-z0-9_-]{16,256}\z/D',$data['partner_key']??'')||!is_string($data['access_token']??null))throw new RuntimeException('Sandbox connection identity changed.');
  config(['marketplace.shopee.environment'=>'sandbox','marketplace.shopee.partner_id'=>$data['application_id'],'marketplace.shopee.partner_key'=>$data['partner_key']]);
  $stage='provider';$info=app(App\Services\Marketplace\ShopeeClient::class)->shopInfo($data['access_token'],$data['shop_id']);
  $actor=App\Models\User::where('email','hoongyaet1995@gmail.com')->where('is_admin',true)->firstOrFail();
  $stage='persist';$id=$db::transaction(function()use($db,$data,$info,$actor){
   $key=['platform'=>'shopee','environment'=>'sandbox','external_shop_id'=>$data['shop_id']];
   $prior=$db::table('marketplace_stores')->where($key)->lockForUpdate()->first();
   if($prior&&App\Models\MarketplaceStoreToken::where('store_id',$prior->id)->where('credential_source','!=','sandbox_copy')->exists())throw new RuntimeException('Existing local credentials cannot be replaced.');
   $values=['shop_name'=>$info['shop_name'],'region'=>'MY','status'=>'connected','updated_at'=>now()];
   if($prior){$id=$prior->id;$db::table('marketplace_stores')->where('id',$id)->update($values);}
   else $id=$db::table('marketplace_stores')->insertGetId($key+$values+['created_at'=>now()]);
   App\Models\MarketplaceStoreToken::updateOrCreate(['store_id'=>$id],['application_id'=>$data['application_id'],'credential_source'=>'sandbox_copy',
    'access_token'=>$data['access_token'],'access_expires_at'=>$data['access_expires_at'],'refresh_token'=>null,'refresh_expires_at'=>null]);
   app(App\Services\Inventory\TestDataClassificationService::class)->markTest('store',(int)$id,$actor,'Existing authorized Shopee sandbox access copied over SSH for local acceptance; no local OAuth acceptance claim.');
   return (int)$id;
  });
  $stage='local_environment';$path=base_path('.env');$env=file_get_contents($path);
  foreach(['MARKETPLACE_SHOPEE_ENVIRONMENT'=>'sandbox','MARKETPLACE_SHOPEE_SANDBOX_PARTNER_ID'=>$data['application_id'],
    'MARKETPLACE_SHOPEE_SANDBOX_PARTNER_KEY'=>$data['partner_key'],'MARKETPLACE_SHOPEE_TEST_STORE_ID'=>(string)$id] as $name=>$value){
   $pattern='/^'.preg_quote($name,'/').'=.*$/m';
   $env=preg_match($pattern,$env)?preg_replace_callback($pattern,fn()=>$name.'='.$value,$env):$env.PHP_EOL.$name.'='.$value.PHP_EOL;
  }
  if(file_put_contents($path,$env,LOCK_EX)===false)throw new RuntimeException('Local sandbox configuration could not be saved.');
  echo json_encode(['local_store_id'=>$id,'provider_identity_verified'=>true,'credential_source'=>'sandbox_copy','refresh_token_copied'=>false,'production_key_preserved'=>true]).PHP_EOL;
 }else{
  $stage='remote_identity';
  $store=$db::table('marketplace_stores')->where('id',1)->first();
  if(config('marketplace.shopee.environment')!=='sandbox'||!$store||$store->platform!=='shopee'||$store->environment!=='sandbox'||(string)$store->external_shop_id!=='227482706')throw new RuntimeException('Expected authorized sandbox store missing.');
  $stage='remote_token';
  $access=app(App\Services\Marketplace\ShopeeTokenManager::class)->accessToken(1);
  $token=App\Models\MarketplaceStoreToken::where('store_id',1)->firstOrFail();
  echo json_encode(['shop_id'=>$store->external_shop_id,'environment'=>'sandbox','application_id'=>$token->application_id,
    'partner_key'=>config('marketplace.shopee.partner_key'),'access_token'=>$access,'access_expires_at'=>$token->access_expires_at?->toIso8601String()],JSON_THROW_ON_ERROR);
 }
}catch(Throwable $e){
 $reason='unclassified';foreach(['expired','application','refresh','configured','authorization'] as $label)if(str_contains(strtolower($e->getMessage()),$label)){$reason=$label;break;}
 file_put_contents('php://stderr',json_encode(['stage'=>$stage,'class'=>get_class($e),'reason'=>$reason]).PHP_EOL);exit(1);
}
