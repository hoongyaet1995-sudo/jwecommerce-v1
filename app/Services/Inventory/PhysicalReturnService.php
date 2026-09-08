<?php
namespace App\Services\Inventory;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhysicalReturnService
{
    /** Caller holds return/variant locks in its receiving transaction. */
    public function receive(ReturnRequest $return,int $quantity,User $actor):int
    {
        if($quantity<1||$quantity>(int)$return->quantity)throw new RuntimeException('Invalid received return quantity.');
        $allocation=DB::table('stock_allocations')->where('owner_type','order')->where('owner_id',$return->order_id)
            ->where('line_key','item:'.$return->order_item_id)->lockForUpdate()->first();
        if(!$allocation?->inventory_balance_id){
            if(DB::table('inventory_configuration')->where('id',1)->value('accounting_enabled'))throw new RuntimeException('Return requires original warehouse allocation reconciliation.');
            return $quantity;
        }
        if($allocation->status!=='consumed')throw new RuntimeException('Return must reference goods already dispatched from a warehouse.');
        $existing=DB::table('inventory_return_movements')->where('return_request_id',$return->id)->first();
        if($existing){
            if((int)$existing->quantity!==$quantity)throw new RuntimeException('Return receipt quantity conflicts.');
            if($existing->available_delta===null)throw new RuntimeException('Historical return availability requires reconciliation.');
            return (int)$existing->available_delta;
        }
        $received=(int)DB::table('inventory_return_movements')->where('stock_allocation_id',$allocation->id)->sum('quantity');
        if($received+$quantity>(int)$allocation->quantity)throw new RuntimeException('Received returns exceed dispatched allocation.');
        $balance=DB::table('inventory_balances')->where('id',$allocation->inventory_balance_id)->lockForUpdate()->first();
        if(!$balance||$balance->physical_stock===null)throw new RuntimeException('Return warehouse requires physical reconciliation.');
        $after=(int)$balance->physical_stock+$quantity;
        if($after>2147483647)throw new RuntimeException('Return exceeds supported stock quantity.');
        $reader=app(PhysicalBalanceReader::class);
        $beforeProjection=$reader->project($balance);
        if(!$beforeProjection['verified'])throw new RuntimeException('Return warehouse obligations require reconciliation.');
        $projected=clone $balance;$projected->physical_stock=$after;
        $availableDelta=$reader->project($projected)['atp']-$beforeProjection['atp'];
        $version=(int)$balance->version+1;
        DB::table('inventory_balances')->where('id',$balance->id)->update(['physical_stock'=>$after,'version'=>$version,'updated_at'=>now()]);
        DB::table('inventory_return_movements')->insert(['return_request_id'=>$return->id,'stock_allocation_id'=>$allocation->id,
            'inventory_balance_id'=>$balance->id,'actor_id'=>$actor->id,'quantity'=>$quantity,'available_delta'=>$availableDelta,'physical_after'=>$after,'balance_version'=>$version,'created_at'=>now()]);
        return $availableDelta;
    }
}
