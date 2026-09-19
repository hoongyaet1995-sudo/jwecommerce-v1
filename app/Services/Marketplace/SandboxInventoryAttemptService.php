<?php
namespace App\Services\Marketplace;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SandboxInventoryAttemptService
{
 public function __construct(private SandboxInventoryWriteGuard $guard,private SandboxStockTransport $transport){}
 public function inspect(string $key,User $actor):array {
  $row=DB::table('sandbox_inventory_attempts')->find($key);abort_unless($row,404);
  abort_unless((int)$row->actor_id===(int)$actor->id&&User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
  return $this->receipt($row);
 }
 public function recheck(string $key,User $actor):array {
  $receipt=$this->inspect($key,$actor);
  abort_unless($receipt['status']==='uncertain',409,'Only an uncertain attempt can be rechecked.');
  $snapshot=$receipt['snapshot'];
  abort_unless($this->guard->review($receipt['store_id'],$receipt['inventory_balance_id'],$actor)===$snapshot,409,'Test stock review is stale.');
  $quantity=null;$outcome='read_failed';
  try {
   $quantity=$this->transport->observe($snapshot,$actor);
   $outcome=$quantity===$snapshot['desired_quantity']?'matched':'mismatch';
  } catch(\Throwable $error) { /* Do not expose provider messages or credentials. */ }
  return DB::transaction(function()use($key,$actor,$snapshot,$quantity,$outcome){
   // Revalidate after the provider read, before applying the observation.
   abort_unless($this->guard->review($snapshot['store_id'],$snapshot['inventory_balance_id'],$actor)===$snapshot,409,'Test stock review is stale.');
   $row=DB::table('sandbox_inventory_attempts')->where('id',$key)->lockForUpdate()->first();
   abort_unless($row->status==='uncertain',409,'Attempt changed. Reload the receipt.');
   DB::table('sandbox_inventory_rechecks')->insert(['attempt_id'=>$key,'actor_id'=>$actor->id,
    'observed_quantity'=>$quantity,'outcome'=>$outcome,'created_at'=>now()]);
   // Preserve the initial readback and error; the append-only observation explains recovery.
   if($outcome==='matched')DB::table('sandbox_inventory_attempts')->where('id',$key)->update(['status'=>'confirmed','updated_at'=>now()]);
   return $this->receipt(DB::table('sandbox_inventory_attempts')->find($key));
  });
 }
 public function stage(int $storeId,int $balanceId,string $key,string $reason,User $actor):array {
  abort_unless(Str::isUuid($key)&&trim($reason)!==''&&mb_strlen($reason)<=255,422,'Review the test operation reason and identity.');
  $hash=hash('sha256',json_encode([$storeId,$balanceId,$reason,$actor->id],JSON_THROW_ON_ERROR));
  return DB::transaction(function()use($storeId,$balanceId,$key,$reason,$actor,$hash){
   $snapshot=$this->guard->review($storeId,$balanceId,$actor);
   $prior=DB::table('sandbox_inventory_attempts')->where('id',$key)->first();
   if($prior){abort_unless(hash_equals($prior->request_hash,$hash),409,'Test operation identity conflicts.');return $this->receipt($prior);}
   DB::table('sandbox_inventory_attempts')->insert(['id'=>$key,'request_hash'=>$hash,'store_id'=>$storeId,'inventory_balance_id'=>$balanceId,
    'actor_id'=>$actor->id,'reason'=>$reason,'status'=>'staged','snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
   return $this->receipt(DB::table('sandbox_inventory_attempts')->find($key));
  });
 }
 public function dispatch(string $key,User $actor):array {
  $receipt=$this->inspect($key,$actor);
  $lock=\Illuminate\Support\Facades\Cache::lock('marketplace-stock:'.$receipt['store_id'].':'.$receipt['snapshot']['listing_variant_id'],3600);
  abort_unless($lock->get(),409,'Another stock update for this listing is running. Retry after it finishes.');
  try {return $this->dispatchLocked($key,$actor);}
  finally {$lock->release();}
 }
 private function dispatchLocked(string $key,User $actor):array {
  abort_unless(config('marketplace.sandbox_stock_writes_enabled')===true,403,'Sandbox inventory writes are disabled.');
  $row=DB::table('sandbox_inventory_attempts')->find($key);abort_unless($row,404);
  abort_unless((int)$row->actor_id===(int)$actor->id&&User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
  if($row->status==='confirmed')return $this->receipt($row);
  abort_unless($row->status==='staged',409,'This attempt cannot be resent. Read provider state before recovery.');
  $snapshot=json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR);
  $current=$this->guard->review((int)$row->store_id,(int)$row->inventory_balance_id,$actor);
  abort_unless($current===$snapshot,409,'Test stock review is stale.');
  abort_unless(DB::table('sandbox_inventory_attempts')->where('id',$key)->where('status','staged')->update(['status'=>'reading','updated_at'=>now()])===1,409,'Test attempt is already running.');
  $sending=false;
  try {
   $before=$this->transport->observe($snapshot,$actor);
   DB::table('sandbox_inventory_attempts')->where('id',$key)->update(['before_quantity'=>$before,'status'=>'sending','updated_at'=>now()]);
   $sending=true;$this->transport->write($snapshot,$actor);
   $after=$this->transport->observe($snapshot,$actor);
   $confirmed=$after===$snapshot['desired_quantity'];
   DB::table('sandbox_inventory_attempts')->where('id',$key)->update(['observed_quantity'=>$after,'status'=>$confirmed?'confirmed':'uncertain',
    'error_code'=>$confirmed?null:'provider_readback_mismatch','updated_at'=>now()]);
  }catch(\Throwable $error){
   DB::table('sandbox_inventory_attempts')->where('id',$key)->update(['status'=>$sending?'uncertain':'rejected',
    'error_code'=>$sending?'provider_result_unconfirmed':'preflight_failed','updated_at'=>now()]);
   // Provider messages may contain signed URLs. Only return stable local audit codes.
  }
  return $this->receipt(DB::table('sandbox_inventory_attempts')->find($key));
 }
 private function receipt(object $row):array {
  return ['id'=>$row->id,'store_id'=>(int)$row->store_id,'inventory_balance_id'=>(int)$row->inventory_balance_id,
   'actor_id'=>(int)$row->actor_id,'reason'=>$row->reason,'status'=>$row->status,'snapshot'=>json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR),
   'before_quantity'=>$row->before_quantity===null?null:(int)$row->before_quantity,'observed_quantity'=>$row->observed_quantity===null?null:(int)$row->observed_quantity,
   'error_code'=>$row->error_code,'created_at'=>$row->created_at,'updated_at'=>$row->updated_at,
   'rechecks'=>DB::table('sandbox_inventory_rechecks')->where('attempt_id',$row->id)->orderBy('id')->get()->all()];
 }
}
