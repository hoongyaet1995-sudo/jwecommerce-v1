<?php
namespace App\Services\Marketplace;
use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class LazadaTestConnectionService {
 public function __construct(private LazadaClient $client){}
 /** Renew only the internal test lease after a read-only provider identity check. */
 public function renew(int $storeId,\App\Models\User $actor,string $reason):array {
  if (!$actor->exists || !\App\Models\User::whereKey($actor->id)->where('is_admin',true)->exists()
      || trim($reason)==='' || mb_strlen($reason)>255) throw new RuntimeException('Authenticated administrator and reason required.');
  if (config('marketplace.lazada.enabled')!==true || (string)config('marketplace.lazada.app_key')!=='139209'
      || (!app()->environment('local','testing') && (config('marketplace.lazada.online_test_access_enabled')!==true
          || (string)config('marketplace.lazada.test_store_id')!==(string)$storeId)))
      throw new RuntimeException('Official test lease renewal is disabled for this store.');
  $receipt=DB::transaction(function()use($storeId,$actor,$reason){
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $token=MarketplaceStoreToken::where('store_id',$storeId)->lockForUpdate()->first();
   if (!$store || $store->platform!=='lazada' || $store->environment!=='live' || $store->region!=='MY'
       || $store->external_shop_id!=='300163725140' || $store->status!=='connected'
       || $store->data_classification!=='test' || !$store->classified_by || !$store->classified_at
       || !$token || $token->credential_source!=='official_test' || $token->application_id!=='139209' || !$token->access_token)
       throw new RuntimeException('Existing classified official test connection required.');
   $seller=$this->client->read('/seller/get',$token->access_token);
   if ((string)($seller['seller_id']??'')!==$store->external_shop_id || ($seller['short_code']??'')!=='MY4NA1T7CK'
       || ($seller['status']??'')!=='ACTIVE') throw new RuntimeException('Fresh official test seller validation failed.');
   $token->test_lease_expires_at=now()->addHour();$token->save();
   return ['store_id'=>$storeId,'actor_id'=>(int)$actor->id,'reason'=>trim($reason),
       'test_lease_expires_at'=>$token->test_lease_expires_at->toIso8601String()];
  });
  \Illuminate\Support\Facades\Log::info('Lazada official test lease renewed',$receipt);
  return $receipt;
 }
 /** Internal local tooling only. The one-hour lease is our limit, not provider token expiry. */
 public function connect(#[\SensitiveParameter] string $accessToken):int {
  if(!app()->environment('local','testing')||(string)config('marketplace.lazada.app_key')!=='139209')throw new RuntimeException('Approved local test application required.');
  $seller=$this->client->read('/seller/get',$accessToken);
  if((string)($seller['seller_id']??'')!=='300163725140'||($seller['short_code']??'')!=='MY4NA1T7CK'||($seller['status']??'')!=='ACTIVE')throw new RuntimeException('Approved test seller identity required.');
  return DB::transaction(function()use($accessToken,$seller){
   $key=['platform'=>'lazada','environment'=>'live','external_shop_id'=>(string)$seller['seller_id']];
   $store=DB::table('marketplace_stores')->where($key)->lockForUpdate()->first();
   if($store && MarketplaceStoreToken::where('store_id',$store->id)->where('credential_source','oauth')->exists())throw new RuntimeException('Cannot replace OAuth credentials with test credentials.');
   $values=['shop_name'=>'[Official Test] '.$seller['name'],'region'=>'MY','account_mode'=>'test','status'=>'connected','updated_at'=>now()];
   if($store){$id=$store->id;DB::table('marketplace_stores')->where('id',$id)->update($values);}
   else $id=DB::table('marketplace_stores')->insertGetId($key+$values+['created_at'=>now()]);
   DB::table('marketplace_store_capabilities')->updateOrInsert(['store_id'=>$id],[
    'catalog_read'=>true,'product_publish'=>false,'order_receive'=>false,'inventory_update'=>false,
    'fulfillment_write'=>false,'updated_at'=>now(),'created_at'=>now()]);
   MarketplaceStoreToken::updateOrCreate(['store_id'=>$id],['application_id'=>'139209','credential_source'=>'official_test',
    'test_lease_expires_at'=>now()->addHour(),'access_token'=>$accessToken,'refresh_token'=>null,'access_expires_at'=>null,'refresh_expires_at'=>null]);
   return (int)$id;
  });
 }
}
