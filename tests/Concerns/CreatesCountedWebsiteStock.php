<?php
namespace Tests\Concerns;

use App\Models\ProductVariant;
use App\Services\Inventory\InventoryBalanceProvisioner;
use Illuminate\Support\Facades\DB;

trait CreatesCountedWebsiteStock
{
    private function countedWebsiteVariant(ProductVariant $variant, int $quantity): ProductVariant
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Inventory fixtures require isolated SQLite memory.');
        }
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update([
            'current_stock'=>$quantity,'physical_stock'=>$quantity,'counted_at'=>now(),
        ]);
        return $variant;
    }
}
