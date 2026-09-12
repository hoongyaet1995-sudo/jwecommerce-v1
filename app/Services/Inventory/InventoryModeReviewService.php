<?php
namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryModeReviewService
{
    public function review(string $target,string $strategy,string $reason,array $explicit,User $actor):array
    {
        abort_unless(config('marketplace.advanced_wms_enabled'),403,'Advanced WMS mode conversion is disabled.');
        if(!in_array($target,['shared_pool','independent_warehouses'],true)
            ||!in_array($strategy,['primary_all','explicit_distribution'],true)||trim($reason)===''||mb_strlen($reason)>255)
            $this->reject('Review the target mode and reason.');
        return DB::transaction(function()use($target,$strategy,$reason,$explicit,$actor){
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            if(!$configuration)$this->reject('Inventory configuration is missing.');
            if($configuration->operating_mode===$target)$this->reject('The target mode is already selected.');
            $captured=app(InventoryModeSnapshot::class)->capture();
            $snapshot=$captured['snapshot'];
            if($snapshot['open_transfers'])$this->reject('Resolve open transfers before changing architecture.');
            $pools=collect($snapshot['pools'])->keyBy('id');
            $sourceKind=match($configuration->operating_mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
            if($sourceKind===null){
                $kinds=collect($snapshot['balances'])->map(fn($balance)=>$pools[$balance['inventory_pool_id']]['kind'])->unique()->values();
                if($kinds->count()>1)$this->reject('Initial mixed inventory pools require explicit source ownership reconciliation.');
                $sourceKind=$kinds->first();
            }
            $totals=[];
            $reservedByBalance=[];
            foreach($snapshot['reservations'] as $reservation){
                $balanceId=$reservation['inventory_balance_id'];
                if($balanceId===null)$this->reject('Reconcile unallocated reservations before reviewing architecture.');
                $reservedByBalance[$balanceId]=($reservedByBalance[$balanceId]??0)+(int)$reservation['quantity'];
            }
            foreach($snapshot['balances'] as $balance){
                if($pools[$balance['inventory_pool_id']]['kind']!==$sourceKind)continue;
                if($balance['physical_stock']===null||$balance['counted_at']===null||$balance['reserved_stock']===null
                    ||$balance['safety_stock']===null||$balance['obligations_reconciled_at']===null)
                    $this->reject('Reconcile all source balances before reviewing architecture.');
                if((int)$balance['reserved_stock']!==($reservedByBalance[$balance['id']]??0))
                    $this->reject('Reconcile source reservation totals before reviewing architecture.');
                $id=$balance['product_variant_id'];
                $totals[$id]=($totals[$id]??0)+(int)$balance['physical_stock'];
            }
            $skuTotals=[];
            foreach($snapshot['variants'] as $variant){
                if(!array_key_exists($variant['id'],$totals)){
                    if($variant['enabled'])$this->reject('Every enabled SKU needs a reviewed source balance.');
                    continue;
                }
                if(!$variant['sku']||array_key_exists($variant['sku'],$skuTotals))$this->reject('Mode allocation requires unique SKU identities.');
                $skuTotals[$variant['sku']]=$totals[$variant['id']];
            }
            $active=collect($snapshot['warehouses'])->where('status','active');
            $primary=$configuration->default_warehouse_id;
            $plan=$target==='independent_warehouses'
                ?app(InventoryModeAllocationPlan::class)->build($skuTotals,$active->pluck('id')->all(),$strategy,$primary===null?null:(int)$primary,$explicit)
                :array_map(fn($total)=>['shared'=>$total],$skuTotals);
            $id=(string)Str::uuid();$expires=now()->addMinutes(15);
            DB::table('inventory_mode_reviews')->insert(['id'=>$id,'actor_id'=>$actor->id,'configuration_version'=>$configuration->version,
                'source_mode'=>$configuration->operating_mode,'target_mode'=>$target,'strategy'=>$strategy,'reason'=>trim($reason),
                'snapshot_hash'=>$captured['hash'],'allocation_plan'=>json_encode($plan,JSON_THROW_ON_ERROR),
                'review_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'expires_at'=>$expires,'created_at'=>now()]);
            return ['review_id'=>$id,'target_mode'=>$target,'configuration_version'=>(int)$configuration->version,
                'allocation_plan'=>$plan,'expires_at'=>$expires->toIso8601String(),'actor_id'=>$actor->id];
        });
    }

    private function reject(string $message):never
    {
        throw ValidationException::withMessages(['mode'=>$message]);
    }
}
