<?php
namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit staging only; neither mode selection nor stock takeover. */
class InventoryBalanceProvisioner
{
    public function provision(int $variantId, string $kind, ?int $warehouseId): array
    {
        if (!in_array($kind,['shared','warehouse'],true) || ($kind==='shared' && $warehouseId!==null)
            || ($kind==='warehouse' && (!$warehouseId || $warehouseId<1))) {
            throw ValidationException::withMessages(['pool_kind'=>'Choose a shared pool or a physical warehouse.']);
        }
        return DB::transaction(function () use ($variantId,$kind,$warehouseId) {
            abort_unless(DB::table('product_variants')->where('id',$variantId)->lockForUpdate()->first(),404);
            if ($warehouseId!==null) {
                abort_unless(DB::table('inventory_warehouses')->where('id',$warehouseId)->lockForUpdate()->first(),404);
            }
            $key=$kind==='shared'?'shared':'warehouse:'.$warehouseId;
            DB::table('inventory_pools')->upsert([
                ['pool_key'=>$key,'kind'=>$kind,'warehouse_id'=>$warehouseId,'created_at'=>now(),'updated_at'=>now()],
            ],['pool_key'],['pool_key']);
            $pool=DB::table('inventory_pools')->where('pool_key',$key)->lockForUpdate()->first();
            if (!$pool || $pool->kind!==$kind || ($pool->warehouse_id===null?null:(int)$pool->warehouse_id)!==$warehouseId) {
                throw ValidationException::withMessages(['pool_kind'=>'Inventory pool identity requires repair before configuration.']);
            }
            DB::table('inventory_balances')->upsert([
                ['inventory_pool_id'=>$pool->id,'product_variant_id'=>$variantId,'created_at'=>now(),'updated_at'=>now()],
            ],['inventory_pool_id','product_variant_id'],['product_variant_id']);
            return app(PhysicalBalanceReader::class)->read((int)$pool->id,$variantId);
        },3);
    }
}
