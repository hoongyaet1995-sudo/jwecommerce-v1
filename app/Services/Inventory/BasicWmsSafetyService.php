<?php
namespace App\Services\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BasicWmsSafetyService {
 public function set(int $balanceId,int $version,int $safety,string $reason,string $key,User $actor):array {
  abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS is disabled.');
  abort_unless($actor->exists&&User::whereKey($actor->id)->where('is_admin',true)->exists(),403);
  if($version<0||$safety<0||$safety>2147483647||trim($reason)===''||mb_strlen($reason)>255||!Str::isUuid($key))$this->reject('Invalid safety stock review.');
  $hash=hash('sha256',json_encode([$balanceId,$version,$safety,$reason,$actor->id],JSON_THROW_ON_ERROR));
  return DB::transaction(function()use($balanceId,$version,$safety,$reason,$key,$actor,$hash){
   $config=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
   if($config->accounting_enabled)$this->reject('Active inventory accounting requires the advanced reconciliation workflow.');
   $prior=DB::table('basic_wms_safety_events')->where('operation_key',$key)->first();
   if($prior){if(!hash_equals($prior->request_hash,$hash))$this->reject('Safety operation identity conflicts.');return json_decode($prior->receipt,true,512,JSON_THROW_ON_ERROR);}
   $identity=DB::table('inventory_balances')->find($balanceId);if(!$identity)$this->reject('Balance not found.');
   DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
   $balance=DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
   if((int)$balance->version!==$version)$this->reject('Balance changed. Reload and review safety stock.');
   if($balance->physical_stock===null||$balance->counted_at===null)$this->reject('Record a physical count first.');
   $owned=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balanceId)->where('status','reserved')->sum('quantity');
   $next=clone $balance;$next->safety_stock=$safety;
   $receipt=['operation_key'=>$key,'balance_id'=>$balanceId,'balance_version'=>$version+1,
    'previous_safety_stock'=>$balance->safety_stock===null?null:(int)$balance->safety_stock,'safety_stock'=>$safety,
    'actor_id'=>(int)$actor->id,'reason'=>$reason,'timestamp'=>now()->toIso8601String(),
    'basic_wms'=>app(BasicWmsBalanceReader::class)->project($next,$owned)];
   DB::table('inventory_balances')->where('id',$balanceId)->update(['safety_stock'=>$safety,'version'=>$version+1,'updated_at'=>now()]);
   DB::table('basic_wms_safety_events')->insert(['operation_key'=>$key,'inventory_balance_id'=>$balanceId,'actor_id'=>$actor->id,
    'request_hash'=>$hash,'receipt'=>json_encode($receipt,JSON_THROW_ON_ERROR),'created_at'=>now()]);
   return $receipt;
  },3);
 }
 private function reject(string $message):never {throw ValidationException::withMessages(['inventory'=>$message]);}
}
