<?php
namespace App\Services\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReconciliationService
{
 public function reconcile(int $balanceId,int $version,int $stockVersion,int $safety,string $key,string $reason,User $actor):array
 {
  abort_unless(config('marketplace.advanced_wms_enabled'),403,'Advanced WMS reconciliation is disabled.');
  if(!$actor->exists||!User::whereKey($actor->id)->where('is_admin',true)->exists())$this->reject('An inventory administrator is required.');
  if($version<0||$stockVersion<0||$safety<0||$safety>2147483647||!preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/',$key)||trim($reason)===''||mb_strlen($reason)>255)$this->reject('Invalid reconciliation request.');
  $hash=hash('sha256',json_encode([$balanceId,$version,$stockVersion,$safety,$reason,$actor->id],JSON_THROW_ON_ERROR));
  return DB::transaction(function()use($balanceId,$version,$stockVersion,$safety,$key,$reason,$actor,$hash){
   $config=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
   $prior=DB::table('inventory_reconciliations')->where('operation_key',$key)->first();
   if($prior){if(!hash_equals($prior->request_hash,$hash))$this->reject('Reconciliation identity conflicts.');return json_decode($prior->after,true,512,JSON_THROW_ON_ERROR);}
   $identity=DB::table('inventory_balances')->find($balanceId);
   if(!$identity)$this->reject('Balance not found.');
   $variant=DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
   $balance=DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
   if((int)$balance->version!==$version||(int)$variant->stock_version!==$stockVersion)$this->reject('Inventory changed. Review reconciliation again.');
   if($balance->physical_stock===null||$balance->counted_at===null)$this->reject('Count physical stock first.');
   if(DB::table('stock_allocations')->where('product_variant_id',$variant->id)->where('status','reserved')->whereNull('inventory_balance_id')->exists())$this->reject('Open reservations require warehouse ownership.');
   $items=DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('i.product_variant_id',$variant->id)
    ->whereIn('o.status',['pending_payment','paid','processing'])->select('i.id','i.order_id','i.quantity')->get();
   foreach($items as $item){
    if(!DB::table('stock_allocations')->where('owner_type','order')->where('owner_id',$item->order_id)->where('line_key','item:'.$item->id)
      ->where('product_variant_id',$variant->id)->where('status','reserved')->where('quantity',$item->quantity)->whereNotNull('inventory_balance_id')->exists())$this->reject('Open website order requires allocation reconciliation.');
   }
   $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balanceId)->where('status','reserved')->sum('quantity');
   if($reserved>2147483647)$this->reject('Reserved quantity exceeds supported bounds.');
   $reader=app(PhysicalBalanceReader::class);$before=$reader->project($balance);
   $next=clone $balance;$next->reserved_stock=$reserved;$next->safety_stock=$safety;$next->obligations_reconciled_at=now()->toDateTimeString();$next->version=$version+1;
   $after=$reader->project($next);
   if($config->accounting_enabled){
    $pool=DB::table('inventory_pools')->find($balance->inventory_pool_id);
    $kind=match($config->operating_mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
    if(!$kind||!$pool||!$before['verified']||$pool->kind!==$kind)$this->reject('Active balance coverage must be restored before changing safety stock.');
    app(InventoryMutationService::class)->change((int)$variant->id,$after['atp']-$before['atp'],'reconcile:'.hash('sha256',$key),'Warehouse obligations reconciled',(int)$actor->id);
   }
   DB::table('inventory_balances')->where('id',$balanceId)->update(['reserved_stock'=>$reserved,'safety_stock'=>$safety,'obligations_reconciled_at'=>$next->obligations_reconciled_at,'version'=>$version+1,'updated_at'=>now()]);
   DB::table('inventory_reconciliations')->insert(['inventory_balance_id'=>$balanceId,'actor_id'=>$actor->id,'operation_key'=>$key,'request_hash'=>$hash,
    'before'=>json_encode($before,JSON_THROW_ON_ERROR),'after'=>json_encode($after,JSON_THROW_ON_ERROR),'reason'=>$reason,'created_at'=>now()]);
   return $after;
  },3);
 }
 private function reject(string $message):never {throw ValidationException::withMessages(['inventory'=>$message]);}
}
