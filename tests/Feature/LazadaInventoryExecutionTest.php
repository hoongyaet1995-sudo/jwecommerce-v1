<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Marketplace\LazadaInventoryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class LazadaInventoryExecutionTest extends TestCase
{
 use RefreshDatabase;
 public function test_order_before_first_count_is_excluded_without_retroactive_reservation():void
 {
  DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_stores']);
  $variant=Product::create(['title'=>'Mapped','handle'=>'historical','base_price'=>110])->variants()->create(['sku'=>'HISTORICAL','title'=>'One','price'=>110,'stock'=>0]);
  $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'TEST','name'=>'Test','status'=>'active','is_active'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','region'=>'MY','environment'=>'sandbox','status'=>'connected','shop_name'=>'Test']);
  DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'platform_location_id'=>'MY-WH-1','is_enabled'=>true]);
  $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'200','external_variant_id'=>'300','observed_data'=>'{}','fetched_at'=>now()]);
  DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
  $created=\Carbon\CarbonImmutable::now()->subHour();
  $payload=['created_at'=>$created->format('Y-m-d H:i:s O'),'order_items'=>[['order_item_id'=>'101','product_id'=>'200','sku_id'=>'300']]];
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
  $balance=app(\App\Services\Inventory\StoreManagement\AccountResolver::class)->balance($variant->id,'marketplace:'.$store);
  DB::table('inventory_balances')->where('id',$balance['id'])->update(['physical_stock'=>10,'reserved_stock'=>0,'safety_stock'=>0,'counted_at'=>now()]);
  $boundary=app(\App\Services\Inventory\StoreManagement\InventoryIngestionBoundary::class);
  $this->assertSame([$order],$boundary->unresolvedHistoricalOrderIds($store,$variant->id,now()));
  $line=['line_key'=>'101','external_product_id'=>'200','external_variant_id'=>'300','quantity'=>1,'warehouse_code'=>'MY-WH-1','target'=>'reserved'];
  app(LazadaInventoryExecutor::class)->apply($order,[$line],100,$created);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$order,'line_key'=>'101','status'=>'skipped_historical','stock_allocation_id'=>null]);
  $this->assertDatabaseCount('stock_allocations',0);
  $this->assertSame([],$boundary->unresolvedHistoricalOrderIds($store,$variant->id,now()));
  $actor=\App\Models\User::factory()->create(['is_admin'=>true]);
  $boundary->create($store,$variant->id,$balance['id'],$actor,'Counted Sandbox inventory and excluded old orders');
  $newOrder=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'102','platform_status'=>'pending','platform_updated_at'=>101,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  app(LazadaInventoryExecutor::class)->apply($newOrder,[array_replace($line,['line_key'=>'103'])],101,\Carbon\CarbonImmutable::now()->addMinute());
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$newOrder,'line_key'=>'103','status'=>'reserved']);
  $this->assertDatabaseHas('inventory_balances',['id'=>$balance['id'],'physical_stock'=>10]);
  $this->assertDatabaseCount('stock_allocations',1);
 }
 public function test_unmapped_line_is_recorded_as_skipped_while_mapped_line_reserves_stock():void
 {
  $variant=Product::create(['title'=>'Mapped','handle'=>'mapped','base_price'=>100])->variants()->create(['sku'=>'MAPPED','title'=>'One','price'=>100,'stock'=>8]);
  $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'TEST','name'=>'Test','status'=>'active','is_active'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','region'=>'MY','environment'=>'sandbox','status'=>'connected','shop_name'=>'Test']);
  DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'platform_location_id'=>'MY-WH-1','is_enabled'=>true]);
  $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'200','external_variant_id'=>'300','observed_data'=>'{}','fetched_at'=>now()]);
  DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $line=['line_key'=>'101','external_product_id'=>'200','external_variant_id'=>'300','quantity'=>1,'warehouse_code'=>'MY-WH-1','target'=>'reserved'];
  app(LazadaInventoryExecutor::class)->apply($order,[$line,array_replace($line,['line_key'=>'102','external_product_id'=>'201','external_variant_id'=>'301'])],100);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$order,'line_key'=>'101','status'=>'reserved']);
  $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$order,'line_key'=>'102','status'=>'skipped_unmapped','stock_allocation_id'=>null]);
  $this->assertDatabaseCount('stock_allocations',1);
  $this->assertSame(7,(int)$variant->fresh()->stock);
 }
 public function test_mixed_item_outcomes_preserve_physical_stock_and_replay():void
 {
  $variant=Product::create(['title'=>'Test','handle'=>'test','base_price'=>100])->variants()->create(['sku'=>'TEST','title'=>'One','price'=>100,'stock'=>8]);
  $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'TEST','name'=>'Test','status'=>'active','is_active'=>true]);
  $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'warehouse',$warehouse)['balance_id'];
  DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>10,'reserved_stock'=>0,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
  DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses','accounting_enabled'=>true]);
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','region'=>'MY','environment'=>'live','status'=>'connected','shop_name'=>'Test']);
  DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'platform_location_id'=>'MY-WH-1','is_enabled'=>true]);
  $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'200','external_variant_id'=>'300','observed_data'=>'{}','fetched_at'=>now()]);
  DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'pending','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $line=['line_key'=>'101','external_product_id'=>'200','external_variant_id'=>'300','quantity'=>1,'warehouse_code'=>'MY-WH-1','target'=>'reserved'];
  $plan=[$line,array_replace($line,['line_key'=>'102'])];
  $executor=app(LazadaInventoryExecutor::class);
  $invalid=$plan; $invalid[1]['warehouse_code']='MY-WH-OTHER';
  try { $executor->apply($order,$invalid,100); $this->fail('Wrong warehouse accepted'); }
  catch (\App\Services\Marketplace\MarketplaceInventoryException $error) { $this->assertSame('warehouse_route_mismatch',$error->getMessage()); }
  $this->assertDatabaseCount('stock_allocations',0);
  $this->assertDatabaseCount('marketplace_inventory_items',0);
  $this->assertSame(8,(int)$variant->fresh()->stock);
  $executor->apply($order,$plan,100);
  $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>10,'reserved_stock'=>2]);
  $plan[0]['target']='released'; $plan[1]['target']='consumed';
  $executor->apply($order,$plan,101); $executor->apply($order,$plan,101);
  $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>9,'reserved_stock'=>0]);
  $this->assertSame(7,(int)$variant->fresh()->stock);
  $this->assertDatabaseCount('stock_allocations',2);
  $this->assertDatabaseCount('stock_allocation_events',4);
 }
}
