<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;

/** Caller must enclose this record and stock mutation in the same transaction. */
final class LazadaInventoryItemState
{
 public function record(int $orderId,array $line,int $version,?int $allocationId):void
 {
  DB::transaction(function()use($orderId,$line,$version,$allocationId){
   $order=DB::table('marketplace_orders')->where('id',$orderId)->lockForUpdate()->first();
   if (!$order) throw new MarketplaceInventoryException('invalid_order_lines');
   $target=$line['target'];
   if (!in_array($target,['waiting','reserved','released','consumed'],true)||$version<0) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
   if ($allocationId===null && in_array($target,['reserved','consumed'],true)) throw new MarketplaceInventoryException('missing_inventory_allocation');
   if ($allocationId!==null && !DB::table('stock_allocations')->where('id',$allocationId)->where('owner_type','marketplace_order')->where('owner_id',$orderId)->where('line_key',$line['line_key'])->where('status',$target)->exists()) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
   $key=['order_id'=>$orderId,'line_key'=>$line['line_key']];
   $prior=DB::table('marketplace_inventory_items')->where($key)->lockForUpdate()->first();
   if ($prior) {
    foreach (['external_product_id','external_variant_id','warehouse_code'] as $field) {
     if ($prior->$field!==$line[$field]) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
    }
    if ((in_array($prior->status,['released','consumed'],true)&&$prior->status!==$target)
     || ($prior->stock_allocation_id!==null&&(int)$prior->stock_allocation_id!==$allocationId)
     || ($prior->status==='reserved'&&$target==='waiting')) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
    if ($version<(int)$prior->platform_updated_at) throw new MarketplaceInventoryException('stale_order_item');
   }
   DB::table('marketplace_inventory_items')->updateOrInsert($key,[
    'external_product_id'=>$line['external_product_id'],'external_variant_id'=>$line['external_variant_id'],
    'warehouse_code'=>$line['warehouse_code'],'status'=>$target,'platform_updated_at'=>$version,
    'stock_allocation_id'=>$allocationId,'created_at'=>$prior?->created_at??now(),'updated_at'=>now(),
   ]);
  });
 }
}
