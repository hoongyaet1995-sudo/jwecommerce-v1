<?php
// Real saved provider order + synthetic physical test stock. All DB changes roll back.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (!app()->environment('local')) exit(1);
$store=DB::table('marketplace_stores')->where('platform','lazada')->where('external_shop_id','300163725140')->first();
if (!$store) exit(2);
$order=DB::table('marketplace_orders')->where('store_id',$store->id)->where('external_order_id','512827127743475')->first();
if (!$order) exit(2);
$sourcePayload=json_decode(Illuminate\Support\Facades\Crypt::decryptString($order->encrypted_payload),true,512,JSON_THROW_ON_ERROR);
$sourceStatuses=array_values(array_unique(array_column($sourcePayload['order_items']??[],'status')));
$baseline=DB::table('stock_allocations')->where('owner_type','marketplace_order')->where('owner_id',$order->id)->count();
if ($baseline!==0) { fwrite(STDERR,"Existing allocations require review.\n"); exit(2); }
DB::beginTransaction();
$ok=false;
try {
 $product=App\Models\Product::create(['title'=>'JW temporary WMS test','handle'=>'jw-transient-lazada-wms','base_price'=>9900,'status'=>'draft']);
 $variant=$product->variants()->create(['sku'=>'JW-TRANSIENT-LZ-WMS','title'=>'Test','price'=>9900,'stock'=>8]);
 $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'JW-TRANSIENT-LZ','name'=>'Temporary test warehouse','status'=>'active','is_active'=>true]);
 $balance=app(App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'warehouse',$warehouse)['balance_id'];
 DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>10,'reserved_stock'=>0,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
 $secondWarehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'JW-TRANSIENT-LZ-2','name'=>'Temporary second warehouse','status'=>'active','is_active'=>true]);
 $secondBalance=app(App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'warehouse',$secondWarehouse)['balance_id'];
 DB::table('inventory_balances')->where('id',$secondBalance)->update(['physical_stock'=>0,'reserved_stock'=>0,'safety_stock'=>0,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
 DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses','accounting_enabled'=>true]);
 DB::table('inventory_fulfillment_routes')->updateOrInsert(['channel_key'=>'marketplace:'.$store->id],['warehouse_id'=>$warehouse,'platform_location_id'=>'MY4NA1T7CK-WH-10007','is_enabled'=>true]);
 $listing=DB::table('marketplace_listing_variants')->where('store_id',$store->id)->where('external_product_id','14971376157')->where('external_variant_id','128558080469')->first();
 if ($listing && DB::table('marketplace_variant_mappings')->where('listing_variant_id',$listing->id)->exists()) throw new RuntimeException('Existing mapping requires review');
 $listingId=$listing?->id??DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store->id,'external_product_id'=>'14971376157','external_variant_id'=>'128558080469','observed_data'=>'{}','fetched_at'=>now()]);
 DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store->id,'listing_variant_id'=>$listingId,'product_variant_id'=>$variant->id]);
 DB::table('marketplace_inventory_policies')->updateOrInsert(['store_id'=>$store->id],['enabled'=>true,'orders_created_after'=>1,'reconciled_at'=>now()]);
 config(['marketplace.inventory_accounting_enabled'=>true]);
 $processor=app(App\Services\Marketplace\MarketplaceOrderInventoryService::class);
 $processor->process($order->id);
 // Changing the route for future orders must never move an existing reservation.
 DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$store->id)->update(['warehouse_id'=>$secondWarehouse]);
 $processor->process($order->id);
 $physical=DB::table('inventory_balances')->find($balance);
 $allocations=DB::table('stock_allocations')->where('owner_type','marketplace_order')->where('owner_id',$order->id)->get(['line_key','quantity','status','inventory_balance_id']);
 $secondPhysical=DB::table('inventory_balances')->find($secondBalance);
 $routeIsolation=$allocations->every(fn($allocation)=>(int)$allocation->inventory_balance_id===(int)$balance)
  && (int)$secondPhysical->physical_stock===0 && (int)$secondPhysical->reserved_stock===0;
 $state=DB::table('marketplace_order_inventory')->where('order_id',$order->id)->first();
 $ok=$state?->status==='reserved' && count($allocations)===2 && (int)$physical->physical_stock===10 && (int)$physical->reserved_stock===2 && (int)$variant->fresh()->stock===6 && $routeIsolation;
 echo json_encode(['database'=>DB::connection()->getDriverName(),'source'=>'saved_real_provider_order',
  'stock_source'=>'synthetic_test_fixture','provider_item_statuses'=>$sourceStatuses,'status'=>$state?->status,'error_code'=>$state?->error_code,
  'physical'=>$physical->physical_stock,'reserved'=>$physical->reserved_stock,'available'=>$variant->fresh()->stock,
  'allocations'=>$allocations,'route_change_preserves_original_warehouse'=>$routeIsolation,'second_warehouse_reserved'=>$secondPhysical->reserved_stock,'passed'=>$ok]).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,'WMS probe failed: '.get_class($error).PHP_EOL); }
finally { DB::rollBack(); }
$restored=DB::table('stock_allocations')->where('owner_type','marketplace_order')->where('owner_id',$order->id)->count()===$baseline;
echo json_encode(['rollback_verified'=>$restored]).PHP_EOL;
exit($ok&&$restored?0:1);
