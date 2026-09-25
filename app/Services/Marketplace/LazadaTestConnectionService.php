<?php
namespace App\Services\Marketplace;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\StoreManagement\StoreEnrollmentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class LazadaTestConnectionService {
 public function __construct(private LazadaClient $client, private StoreEnrollmentService $enrollment){}
 /** Renew only the internal test lease after a read-only provider identity check. */
 public function renew(int $storeId,\App\Models\User $actor,string $reason):array {
  if (!$actor->exists || !\App\Models\User::whereKey($actor->id)->where('is_admin',true)->exists()
      || trim($reason)==='' || mb_strlen($reason)>255) throw new RuntimeException('Authenticated administrator and reason required.');
  $testApp=(string)config('marketplace.lazada.test_app_key');
  if (config('marketplace.lazada.enabled')!==true || !preg_match('/^[1-9][0-9]*$/D',$testApp)
      || (!app()->environment('local','testing') && (config('marketplace.lazada.online_test_access_enabled')!==true
          || (string)config('marketplace.lazada.test_store_id')!==(string)$storeId)))
      throw new RuntimeException('Official test lease renewal is disabled for this store.');
  $retryTasks=[];
  $receipt=DB::transaction(function()use($storeId,$actor,$reason,$testApp,&$retryTasks){
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $token=MarketplaceStoreToken::where('store_id',$storeId)->lockForUpdate()->first();
   if (!$store || $store->platform!=='lazada' || $store->environment!=='live' || $store->region!=='MY'
       || $store->external_shop_id!=='300163725140' || $store->status!=='connected'
       || $store->data_classification!=='test' || !$store->classified_by || !$store->classified_at
       || !$token || $token->credential_source!=='official_test' || $token->application_id!==$testApp || !$token->access_token)
       throw new RuntimeException('Existing classified official test connection required.');
   $seller=$this->client->read('/seller/get',$token->access_token,[],$storeId);
   if ((string)($seller['seller_id']??'')!==$store->external_shop_id || ($seller['short_code']??'')!=='MY4NA1T7CK'
       || ($seller['status']??'')!=='ACTIVE') throw new RuntimeException('Fresh official test seller validation failed.');
   $token->test_lease_expires_at=now()->addHour();$token->save();
   $retryTasks=$this->releaseExpiredInventoryAttempts($storeId);
   return ['store_id'=>$storeId,'actor_id'=>(int)$actor->id,'reason'=>trim($reason),
       'test_lease_expires_at'=>$token->test_lease_expires_at->toIso8601String()];
  });
  $this->retryFacilityInventory($storeId,$retryTasks);
  \Illuminate\Support\Facades\Log::info('Lazada official test lease renewed',$receipt);
  return $receipt;
 }
 /** Connect only the designated borrowed seller after a fresh provider identity check. */
 public function connect(#[\SensitiveParameter] string $accessToken):int {
  if(!app()->environment('local','testing') && config('marketplace.lazada.online_test_access_enabled')!==true) {
   throw new RuntimeException('Official test connection is disabled in this environment.');
  }
  $testApp=(string)config('marketplace.lazada.test_app_key');
  if(!preg_match('/^[1-9][0-9]*$/D',$testApp))throw new RuntimeException('Approved Lazada test application required.');
  $seller=$this->client->readOfficialTest('/seller/get',$accessToken);
  if((string)($seller['seller_id']??'')!=='300163725140'||($seller['short_code']??'')!=='MY4NA1T7CK'||($seller['status']??'')!=='ACTIVE')throw new RuntimeException('Approved test seller identity required.');
  $retryTasks=[];
  $storeId=DB::transaction(function()use($accessToken,$seller,$testApp,&$retryTasks){
   $key=['platform'=>'lazada','environment'=>'live','external_shop_id'=>(string)$seller['seller_id']];
   $store=DB::table('marketplace_stores')->where($key)->lockForUpdate()->first();
   if($store && MarketplaceStoreToken::where('store_id',$store->id)->where('credential_source','oauth')->exists())throw new RuntimeException('Cannot replace OAuth credentials with test credentials.');
   $values=['shop_name'=>'[Official Test] '.$seller['name'],'region'=>'MY','account_mode'=>'test','status'=>'connected','updated_at'=>now()];
   if($store){$id=$store->id;DB::table('marketplace_stores')->where('id',$id)->update($values);}
   else $id=DB::table('marketplace_stores')->insertGetId($key+$values+['created_at'=>now()]);
   DB::table('marketplace_store_capabilities')->updateOrInsert(['store_id'=>$id],[
    'catalog_read'=>true,'product_publish'=>true,'order_receive'=>true,'inventory_update'=>true,
    'fulfillment_write'=>false,'updated_at'=>now(),'created_at'=>now()]);
   MarketplaceStoreToken::updateOrCreate(['store_id'=>$id],['application_id'=>$testApp,'credential_source'=>'official_test',
    'test_lease_expires_at'=>now()->addHour(),'access_token'=>$accessToken,'refresh_token'=>null,'access_expires_at'=>null,'refresh_expires_at'=>null]);
   $retryTasks=$this->releaseExpiredInventoryAttempts($id);
   return (int)$id;
  });
  $this->enrollment->enroll($storeId);
  $this->retryFacilityInventory($storeId,$retryTasks);
  return $storeId;
 }
 private function releaseExpiredInventoryAttempts(int $storeId):array {
  // A fresh seller read proved the replacement credential works. Old auth failures
  // are retryable failures now; their stock writes are never replayed implicitly.
  $retry=DB::table('inventory_store_sync_tasks')->where('marketplace_store_id',$storeId)
   ->where('status','auth_expired')->where('stage','facility')
   ->whereNotNull('marketplace_store_facility_id')
   ->get(['id','marketplace_store_facility_id','product_variant_id'])->all();
  DB::table('inventory_store_sync_tasks')->where('marketplace_store_id',$storeId)
   ->where('status','auth_expired')->update(['status'=>'failed','updated_at'=>now()]);
  return $retry;
 }
 private function retryFacilityInventory(int $storeId,array $retryTasks):void {
  foreach($retryTasks as $oldTask){
   try {
    $facilityId=(int)$oldTask->marketplace_store_facility_id;
    $variantId=(int)$oldTask->product_variant_id;
    $latest=DB::table('inventory_store_sync_tasks')->where('marketplace_store_id',$storeId)
     ->where('marketplace_store_facility_id',$facilityId)->where('product_variant_id',$variantId)
     ->where('status','!=','superseded')->orderByDesc('created_at')->orderByDesc('id')->first();
    if(!$latest || $latest->id!==$oldTask->id)continue;
    $recovery=app(\App\Services\Inventory\StoreManagement\FacilitySyncRecovery::class);
    if(!$recovery->details($storeId,$facilityId,$variantId)['can_resync'])continue;
    $result=$recovery->resync($storeId,$facilityId,$variantId,'lazada-auth-recovery-'.$oldTask->id);
    if($result['task_status']==='facility_pending' && $result['task_id']){
     (new \App\Jobs\ProcessFacilityInventorySync($result['task_id']))
      ->handle(app(\App\Contracts\StoreInventoryPublisher::class));
    }
   }catch(\Throwable $error){
    \Illuminate\Support\Facades\Log::warning('Lazada test stock retry remains pending',[
     'store_id'=>$storeId,'task_id'=>$oldTask->id,'reason'=>$error->getMessage(),
    ]);
   }
  }
 }
}
