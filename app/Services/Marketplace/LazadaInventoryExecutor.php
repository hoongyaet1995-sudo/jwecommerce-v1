<?php
namespace App\Services\Marketplace;
use App\Models\ProductVariant;
use App\Services\Inventory\StockAllocationService;
use Illuminate\Support\Facades\DB;

/** Internal transaction boundary. Ingestion must first enforce policy and parse the complete order. */
final class LazadaInventoryExecutor
{
 public function __construct(private StockAllocationService $allocations,private LazadaInventoryItemState $states) {}
 public function apply(int $orderId,array $plan,int $version,?\Carbon\CarbonInterface $managedCreatedAt=null):void
 {
  DB::transaction(function()use($orderId,$plan,$version,$managedCreatedAt){
   $storeId=DB::table('marketplace_orders')->where('id',$orderId)->value('store_id');
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   if (!$store||$store->platform!=='lazada'||$store->status!=='connected') throw new MarketplaceInventoryException('store_not_connected');
   $order=DB::table('marketplace_orders')->where('id',$orderId)->lockForUpdate()->first();
   $prior=DB::table('marketplace_inventory_items')->where('order_id',$orderId)->get()->keyBy('line_key');
   $keys=array_column($plan,'line_key');
   if (!$plan||count($keys)!==count(array_unique($keys))||array_diff($prior->keys()->all(),$keys)) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
   $held=DB::table('stock_allocations')->where('owner_type','marketplace_order')->where('owner_id',$orderId)->get()->keyBy('line_key');
   if (array_diff($held->keys()->all(),$keys)) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
   foreach ($plan as $line) {
    $key=$line['line_key']; $target=$line['target']; $allocation=$held->get($key); $previous=$prior->get($key);
    if ($previous) {
     if ((int)$previous->platform_updated_at>$version) throw new MarketplaceInventoryException('stale_order_item');
     foreach (['external_product_id','external_variant_id','warehouse_code'] as $field) if ($previous->$field!==$line[$field]) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
     if (in_array($previous->status,['released','consumed'],true)&&$previous->status!==$target) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
    }
    $id=$allocation?(int)$allocation->id:null;
    if ($target==='waiting') {
     if ($allocation) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
    } elseif ($target==='released') {
     if ($allocation) {
      if (!in_array($allocation->status,['reserved','released'],true)) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
      $this->allocations->release($id);
     }
    } elseif (in_array($target,['reserved','consumed'],true)) {
     if ($allocation) {
      if ((int)$allocation->quantity!==1||$allocation->status==='released'||($allocation->status==='consumed'&&$target==='reserved')) throw new MarketplaceInventoryException('inventory_lifecycle_conflict');
     } else {
      $route=DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$storeId)->lockForUpdate()->first();
      if (!$route||!$route->is_enabled||$route->platform_location_id!==$line['warehouse_code']) throw new MarketplaceInventoryException('warehouse_route_mismatch');
      $variant=DB::table('marketplace_listing_variants as l')->join('marketplace_variant_mappings as m','m.listing_variant_id','=','l.id')
       ->where('l.store_id',$storeId)->where('m.store_id',$storeId)->where('l.external_product_id',$line['external_product_id'])->where('l.external_variant_id',$line['external_variant_id'])->value('m.product_variant_id');
      if (!$variant) throw new MarketplaceInventoryException('unmapped_order_line');
      $activationId=null;
      if ($managedCreatedAt!==null) {
       $activation=app(\App\Services\Inventory\ManagedInventoryActivation::class)->eligible('marketplace:'.$storeId,(int)$variant,$managedCreatedAt);
       if (!$activation) throw new MarketplaceInventoryException('order_outside_managed_activation');
       $activationId=(int)$activation['id'];
      }
      $product=ProductVariant::where('id',$variant)->lockForUpdate()->first();
      if (!$product||($activationId===null && $product->stock<1)) throw new MarketplaceInventoryException('insufficient_stock');
      $id=$this->allocations->reserve('marketplace_order',$orderId,$key,(int)$variant,1,$activationId);
     }
     if ($target==='consumed') $this->allocations->consume($id);
    } else throw new MarketplaceInventoryException('unsupported_order_status');
    $this->states->record($orderId,$line,$version,$id);
   }
  });
 }
}
