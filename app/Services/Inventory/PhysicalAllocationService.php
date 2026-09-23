<?php
namespace App\Services\Inventory;
use Illuminate\Support\Facades\DB;
use App\Services\Inventory\WarehouseAllocationException as RuntimeException;

/** Called inside StockAllocationService's transaction with the variant row locked. */
class PhysicalAllocationService
{
    /** Managed reservations use owned allocation rows; raw historical obligations remain unknown. */
    public function managed(int $allocationId,string $event): array
    {
        $allocation=DB::table('stock_allocations')->find($allocationId);
        $activation=DB::table('inventory_order_activations')->find($allocation->inventory_order_activation_id);
        if (!$activation || (int)$activation->product_variant_id!==(int)$allocation->product_variant_id) {
            throw new RuntimeException('Managed allocation does not match its activated SKU.');
        }
        $balance=DB::table('inventory_balances')->where('id',$activation->inventory_balance_id)->lockForUpdate()->first();
        if (!$balance || $balance->physical_stock===null || $balance->safety_stock===null || !$balance->counted_at) {
            throw new RuntimeException('Managed physical stock requires a verified count.');
        }
        $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balance->id)->where('status','reserved')->sum('quantity');
        $physical=(int)$balance->physical_stock;
        $before=max(0,$physical-$reserved-(int)$balance->safety_stock);
        $quantity=(int)$allocation->quantity;
        if ($event==='reserved') {
            if ($physical-$reserved-(int)$balance->safety_stock<$quantity) throw new RuntimeException('Verified warehouse ATP is insufficient.');
            $reserved+=$quantity;
            DB::table('stock_allocations')->where('id',$allocationId)->update(['inventory_balance_id'=>$balance->id]);
        } else {
            $reserved-=$quantity;
            if ($event==='consumed') $physical-=$quantity;
        }
        if ($physical<0 || $reserved<0) throw new RuntimeException('Warehouse allocation exceeds recorded quantities.');
        $version=(int)$balance->version+1;
        DB::table('inventory_balances')->where('id',$balance->id)->update(['physical_stock'=>$physical,'version'=>$version,'updated_at'=>now()]);
        DB::table('inventory_allocation_movements')->insert([
            'stock_allocation_id'=>$allocationId,'inventory_balance_id'=>$balance->id,'event'=>$event,
            'physical_delta'=>$event==='consumed'?-$quantity:0,'reserved_delta'=>$event==='reserved'?$quantity:-$quantity,
            'physical_after'=>$physical,'reserved_after'=>$reserved,'balance_version'=>$version,'created_at'=>now(),
        ]);
        $after=max(0,$physical-$reserved-(int)$balance->safety_stock);
        return ['available'=>$after,'delta'=>$after-$before];
    }

    public function reserve(int $allocationId,string $ownerType,int $ownerId,int $variantId,int $quantity):void
    {
        $config=DB::table('inventory_configuration')->where('id',1)->first();
        if(!$config?->accounting_enabled)return;
        $pool=null;
        if($config->operating_mode==='shared_pool'){
            $pool=DB::table('inventory_pools')->where('pool_key','shared')->where('kind','shared')->first();
        }elseif($config->operating_mode==='independent_warehouses'){
            $channel='website';
            if($ownerType==='marketplace_order'){
                $storeId=DB::table('marketplace_orders')->where('id',$ownerId)->value('store_id');
                if(!$storeId)throw new RuntimeException('Marketplace order store identity is unavailable.');
                $channel='marketplace:'.$storeId;
            }
            $route=DB::table('inventory_fulfillment_routes')->where('channel_key',$channel)->where('is_enabled',true)->first();
            if(!$route||!DB::table('inventory_warehouses')->where('id',$route->warehouse_id)->where('status','active')->exists())throw new RuntimeException('An active fulfillment warehouse route is required.');
            $pool=DB::table('inventory_pools')->where('kind','warehouse')->where('warehouse_id',$route->warehouse_id)->first();
        }
        if(!$pool)throw new RuntimeException('Inventory pool is not configured.');
        $balance=DB::table('inventory_balances')->where('inventory_pool_id',$pool->id)->where('product_variant_id',$variantId)->lockForUpdate()->first();
        if(!$balance)throw new RuntimeException('SKU warehouse balance is not configured.');
        $read=app(PhysicalBalanceReader::class)->project($balance);
        if(!$read['verified']||$read['atp']<$quantity)throw new RuntimeException('Verified warehouse ATP is insufficient.');
        DB::table('stock_allocations')->where('id',$allocationId)->update(['inventory_balance_id'=>$balance->id]);
        $this->change($allocationId,$balance,'reserved',0,$quantity);
    }

    public function finish(object $allocation,string $event):?int
    {
        if(!$allocation->inventory_balance_id){
            if(DB::table('inventory_configuration')->where('id',1)->value('accounting_enabled'))throw new RuntimeException('Existing order allocation requires warehouse reconciliation.');
            return null;
        }
        $balance=DB::table('inventory_balances')->where('id',$allocation->inventory_balance_id)->lockForUpdate()->first();
        if(!$balance)throw new RuntimeException('Allocated warehouse balance is unavailable.');
        $reader=app(PhysicalBalanceReader::class);
        $before=$reader->project($balance);
        if(!$before['verified'])throw new RuntimeException('Allocated warehouse requires obligation reconciliation before completion.');
        $projected=clone $balance;
        $projected->physical_stock=(int)$balance->physical_stock-($event==='consumed'?(int)$allocation->quantity:0);
        $projected->reserved_stock=(int)$balance->reserved_stock-(int)$allocation->quantity;
        $after=$reader->project($projected);
        $this->change((int)$allocation->id,$balance,$event,$event==='consumed'?-(int)$allocation->quantity:0,-(int)$allocation->quantity);
        return $after['atp']-$before['atp'];
    }

    private function change(int $allocationId,object $balance,string $event,int $physicalDelta,int $reservedDelta):void
    {
        if($balance->physical_stock===null||$balance->reserved_stock===null)throw new RuntimeException('Allocated warehouse quantities require reconciliation.');
        $physical=(int)$balance->physical_stock+$physicalDelta;$reserved=(int)$balance->reserved_stock+$reservedDelta;
        if($physical<0||$reserved<0||$physical>2147483647||$reserved>2147483647)throw new RuntimeException('Warehouse allocation exceeds recorded quantities.');
        $version=(int)$balance->version+1;
        DB::table('inventory_balances')->where('id',$balance->id)->update(['physical_stock'=>$physical,'reserved_stock'=>$reserved,'version'=>$version,'updated_at'=>now()]);
        DB::table('inventory_allocation_movements')->insert(['stock_allocation_id'=>$allocationId,'inventory_balance_id'=>$balance->id,'event'=>$event,
            'physical_delta'=>$physicalDelta,'reserved_delta'=>$reservedDelta,'physical_after'=>$physical,'reserved_after'=>$reserved,'balance_version'=>$version,'created_at'=>now()]);
    }
}
