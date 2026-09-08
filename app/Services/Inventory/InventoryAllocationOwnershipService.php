<?php
namespace App\Services\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAllocationOwnershipService
{
 public function assign(int $allocationId,int $balanceId,int $balanceVersion,string $reason,User $actor):array
 {
  if(!$actor->exists||!User::whereKey($actor->id)->where('is_admin',true)->exists())$this->reject('An inventory administrator is required.');
  if($balanceVersion<0||trim($reason)===''||mb_strlen($reason)>255)$this->reject('A valid version and ownership reason are required.');
  return DB::transaction(function()use($allocationId,$balanceId,$balanceVersion,$reason,$actor){
   $config=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
   $identity=DB::table('stock_allocations')->find($allocationId);
   if(!$identity)$this->reject('Reservation not found.');
   DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
   $allocation=DB::table('stock_allocations')->where('id',$allocationId)->lockForUpdate()->first();
   $prior=DB::table('inventory_allocation_ownership_changes')->where('stock_allocation_id',$allocationId)->first();
   if($prior){
    if((int)$prior->inventory_balance_id!==$balanceId||(int)$prior->actor_id!==(int)$actor->id||$prior->reason!==$reason)$this->reject('Reservation already has a different ownership receipt.');
    return (array)$prior;
   }
   if($config->accounting_enabled)$this->reject('Initial ownership assignment must finish before inventory takeover.');
   if($allocation->status!=='reserved'||$allocation->inventory_balance_id!==null)$this->reject('Only an unowned open reservation can be assigned.');
   $balance=DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
   if(!$balance||(int)$balance->product_variant_id!==(int)$allocation->product_variant_id)$this->reject('Reservation and balance must refer to the same SKU.');
   if((int)$balance->version!==$balanceVersion)$this->reject('Balance changed. Review ownership again.');
   if($balance->physical_stock===null||$balance->counted_at===null)$this->reject('Count the physical stock before assigning reservations.');
   $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balanceId)->where('status','reserved')->sum('quantity')+(int)$allocation->quantity;
   if($reserved>2147483647)$this->reject('Reservation total exceeds supported bounds.');
   DB::table('stock_allocations')->where('id',$allocationId)->update(['inventory_balance_id'=>$balanceId,'updated_at'=>now()]);
   DB::table('inventory_balances')->where('id',$balanceId)->update(['reserved_stock'=>$reserved,'obligations_reconciled_at'=>null,'version'=>$balanceVersion+1,'updated_at'=>now()]);
   $id=DB::table('inventory_allocation_ownership_changes')->insertGetId(['stock_allocation_id'=>$allocationId,'inventory_balance_id'=>$balanceId,
    'actor_id'=>$actor->id,'quantity'=>$allocation->quantity,'balance_version'=>$balanceVersion+1,'reason'=>$reason,'created_at'=>now()]);
   return (array)DB::table('inventory_allocation_ownership_changes')->find($id);
  },3);
 }
 private function reject(string $message):never {throw ValidationException::withMessages(['inventory'=>$message]);}
}
