<?php
namespace App\Services\Inventory;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class WebsiteInventoryAvailability
{
    public function quantity(ProductVariant $variant): ?int
    {
        $activation=app(ManagedInventoryActivation::class)->eligible('website',(int)$variant->id,now());
        if (!$activation) return (int)$variant->stock;
        $balance=DB::table('inventory_balances')->find($activation['inventory_balance_id']);
        if (!$balance) return null;
        $reserved=(int)DB::table('stock_allocations')->where('inventory_balance_id',$balance->id)
            ->where('status','reserved')->sum('quantity');
        return app(BasicWmsBalanceReader::class)->project($balance,$reserved)['available'];
    }
}
