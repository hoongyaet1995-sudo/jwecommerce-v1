<?php
namespace App\Services\Inventory;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class WebsiteInventoryAvailability
{
    public function quantity(ProductVariant $variant): ?int
    {
        if (app(\App\Services\Inventory\StoreManagement\RebaselineFreeze::class)->isPaused()) return 0;
        $configuration=DB::table('inventory_configuration')->where('id',1)->first();
        if (config('inventory.current_stock_only')) {
            $poolKey = $configuration?->operating_mode === 'shared_pool' ? 'shared' : 'store:website';
            $quantity = DB::table('inventory_balances as balance')
                ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
                ->where('pool.pool_key', $poolKey)->where('balance.product_variant_id', $variant->id)
                ->value('balance.current_stock');

            return $quantity === null ? null : (int) $quantity;
        }
        if(in_array($configuration?->operating_mode,['shared_pool','independent_stores'],true)
            && DB::table('inventory_store_accounts')->where('account_key','website')->where('management_status','managed')->exists()){
            $poolKey=$configuration->operating_mode==='shared_pool'?'shared':'store:website';
            $balance=DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
                ->where('p.pool_key',$poolKey)->where('b.product_variant_id',$variant->id)
                ->select('b.*')->first();
            if(!$balance||$balance->physical_stock===null||$balance->reserved_stock===null||$balance->safety_stock===null||$balance->counted_at===null)return null;
            return max(0,(int)$balance->physical_stock-(int)$balance->reserved_stock-(int)$balance->safety_stock);
        }
        $activation=app(ManagedInventoryActivation::class)->eligible('website',(int)$variant->id,now());
        if (!$activation) return (int)$variant->stock;
        $balance=DB::table('inventory_balances')->find($activation['inventory_balance_id']);
        if (!$balance) return null;
        $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balance->id)
            ->where('status','reserved')->sum('quantity');
        return app(BasicWmsBalanceReader::class)->project($balance,$reserved)['available'];
    }
}
