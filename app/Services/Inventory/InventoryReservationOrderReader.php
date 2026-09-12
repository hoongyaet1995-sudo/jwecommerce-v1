<?php
namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Order identity only; never expose encrypted provider payloads or customer details. */
class InventoryReservationOrderReader
{
    public function enrich(Collection $allocations): Collection
    {
        $website=DB::table('orders')->whereIn('id',$allocations->where('owner_type','order')->pluck('owner_id'))
            ->get(['id','number','status'])->keyBy('id');
        $marketplace=DB::table('marketplace_orders as o')->join('marketplace_stores as s','s.id','=','o.store_id')
            ->whereIn('o.id',$allocations->where('owner_type','marketplace_order')->pluck('owner_id'))
            ->get(['o.id','o.external_order_id','o.platform_status','o.store_id','s.platform'])->keyBy('id');
        return $allocations->map(function($allocation)use($website,$marketplace){
            $order=$allocation->owner_type==='order'?$website->get($allocation->owner_id):
                ($allocation->owner_type==='marketplace_order'?$marketplace->get($allocation->owner_id):null);
            $allocation->order=$order===null?null:[
                'id'=>(int)$order->id,
                'number'=>$allocation->owner_type==='order'?$order->number:$order->external_order_id,
                'status'=>$allocation->owner_type==='order'?$order->status:$order->platform_status,
                'channel'=>$allocation->owner_type==='order'?'webstore':$order->platform,
                'store_id'=>$allocation->owner_type==='order'?null:(int)$order->store_id,
            ];
            return $allocation;
        });
    }
}
