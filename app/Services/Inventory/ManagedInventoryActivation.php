<?php
namespace App\Services\Inventory;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit ownership for new orders only. Does not reconcile historical demand. */
class ManagedInventoryActivation
{
    public function activate(string $channel,int $balanceId,User $actor,string $reason): array
    {
        if (!$actor->exists || !User::whereKey($actor->id)->where('is_admin',true)->exists()
            || trim($reason)==='' || mb_strlen($reason)>2000) {
            $this->reject('An authenticated administrator and activation reason are required.');
        }
        return DB::transaction(function() use($channel,$balanceId,$actor,$reason){
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            if ($channel!=='website' && (!preg_match('/\Amarketplace:([1-9][0-9]*)\z/',$channel,$match)
                || !DB::table('marketplace_stores')->where('id',$match[1])->whereIn('platform',['shopee','lazada'])->exists())) {
                $this->reject('Choose an existing supported channel.');
            }
            $balance=DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
            if (!$balance || $balance->physical_stock===null || (int)$balance->physical_stock<0
                || $balance->safety_stock===null || (int)$balance->safety_stock<0 || !$balance->counted_at) {
                $this->reject('Count physical inventory and configure safety stock before activation.');
            }
            $pool=DB::table('inventory_pools')->find($balance->inventory_pool_id);
            $kind=match($configuration?->operating_mode){'shared_pool'=>'shared','independent_warehouses'=>'warehouse',default=>null};
            if (!$pool || $kind!==$pool->kind) $this->reject('Configure the inventory mode before activation.');
            if ($kind==='warehouse') {
                $route=DB::table('inventory_fulfillment_routes')->where('channel_key',$channel)->first();
                if (!$route || !$route->is_enabled || (int)$route->warehouse_id!==(int)$pool->warehouse_id
                    || !DB::table('inventory_warehouses')->where('id',$pool->warehouse_id)->where('status','active')->exists()) {
                    $this->reject('Activation requires the channel route to this active warehouse.');
                }
            }
            $existing=DB::table('inventory_order_activations')->where('channel_key',$channel)->where('product_variant_id',$balance->product_variant_id)->first();
            if ($existing) {
                if ((int)$existing->inventory_balance_id!==$balanceId) $this->reject('Existing order ownership cannot be moved by activation.');
                return (array)$existing;
            }
            // Whole-second cutoff is deliberately strict: equal timestamps are excluded.
            $cutoff=now()->startOfSecond();
            $id=DB::table('inventory_order_activations')->insertGetId([
                'channel_key'=>$channel,'product_variant_id'=>$balance->product_variant_id,
                'inventory_balance_id'=>$balanceId,'actor_id'=>$actor->id,'reason'=>trim($reason),
                'orders_created_after'=>$cutoff,'created_at'=>$cutoff,
            ]);
            return (array)DB::table('inventory_order_activations')->find($id);
        },3);
    }

    public function eligible(string $channel,int $variantId,CarbonInterface $createdAt): ?array
    {
        $row=DB::table('inventory_order_activations')->where('channel_key',$channel)
            ->where('product_variant_id',$variantId)->where('orders_created_after','<',$createdAt)->first();
        return $row ? (array)$row : null;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['activation'=>$message]);
    }
}
