<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaInventoryItemState;
use App\Services\Marketplace\MarketplaceInventoryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LazadaInventoryItemStateTest extends TestCase
{
 use RefreshDatabase;
 public function test_cancel_before_reservation_is_a_persistent_terminal_record():void
 {
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','status'=>'connected','shop_name'=>'Test']);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'canceled','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $line=['line_key'=>'101','external_product_id'=>'200','external_variant_id'=>'300','warehouse_code'=>'MY-WH-1','target'=>'released'];
  $states=app(LazadaInventoryItemState::class);
  $states->record($order,$line,100,null);
  $states->record($order,$line,100,null);
  $this->assertDatabaseCount('marketplace_inventory_items',1);
  $this->assertDatabaseCount('stock_allocations',0);
  $this->expectException(MarketplaceInventoryException::class);
  $states->record($order,array_replace($line,['target'=>'reserved']),101,null);
 }
 public function test_different_items_keep_independent_terminal_states():void
 {
  $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','status'=>'connected','shop_name'=>'Test']);
  $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'100','platform_status'=>'canceled','platform_updated_at'=>100,'encrypted_payload'=>'unused','fetched_at'=>now()]);
  $line=['line_key'=>'101','external_product_id'=>'200','external_variant_id'=>'300','warehouse_code'=>'MY-WH-1','target'=>'released'];
  $states=app(LazadaInventoryItemState::class);
  $states->record($order,$line,100,null);
  $states->record($order,array_replace($line,['line_key'=>'102','target'=>'waiting']),100,null);
  $this->assertDatabaseCount('marketplace_inventory_items',2);
  $this->expectException(MarketplaceInventoryException::class);
  $states->record($order,array_replace($line,['warehouse_code'=>'MY-WH-2']),101,null);
 }
}

