<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\StockAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhysicalAllocationFlowTest extends TestCase
{
    use RefreshDatabase;
    public function test_two_marketplace_stores_share_one_warehouse_balance_without_duplicating_atp():void
    {
        $variant=Product::create(['title'=>'Multi store','handle'=>'multi-store','base_price'=>100])->variants()->create(['sku'=>'MULTI','title'=>'One','price'=>100,'stock'=>8]);
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'MULTI','name'=>'Common warehouse','status'=>'active','is_active'=>true]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'warehouse',$warehouse);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>10,'reserved_stock'=>0,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses','accounting_enabled'=>true]);
        $orders=[];
        foreach(['shopee','lazada'] as $platform){
            $store=DB::table('marketplace_stores')->insertGetId(['platform'=>$platform,'shop_name'=>$platform,'region'=>'MY','environment'=>'sandbox']);
            DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'is_enabled'=>true]);
            $orders[]=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'order','platform_status'=>'ready','platform_updated_at'=>1,'encrypted_payload'=>'test fixture','fetched_at'=>now()]);
        }
        $service=app(StockAllocationService::class);
        $first=$service->reserve('marketplace_order',$orders[0],'one',$variant->id,5);
        try{$service->reserve('marketplace_order',$orders[1],'two',$variant->id,4);$this->fail('Shared stock oversold');}
        catch(\RuntimeException $error){$this->assertNotEmpty($error->getMessage());}
        $second=$service->reserve('marketplace_order',$orders[1],'two',$variant->id,3);
        $this->assertDatabaseCount('inventory_balances',1);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>10,'reserved_stock'=>8]);
        $service->consume($first);$service->consume($second);
        $this->assertSame(0,(int)$variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>2,'reserved_stock'=>0]);
    }
    public function test_shared_reserve_consume_release_and_retry_preserve_inventory():void
    {
        $variant=Product::create(['title'=>'Physical allocation','handle'=>'physical-allocation','base_price'=>100])->variants()->create(['sku'=>'ALLOC','title'=>'One','price'=>100,'stock'=>18]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'reserved_stock'=>0,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>true]);
        $service=app(StockAllocationService::class);
        $first=$service->reserve('order',1,'item:1',$variant->id,5);
        $this->assertSame($first,$service->reserve('order',1,'item:1',$variant->id,5));
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>20,'reserved_stock'=>5]);
        $service->consume($first);$service->consume($first);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>15,'reserved_stock'=>0]);
        $second=$service->reserve('order',2,'item:2',$variant->id,3);
        $service->release($second);$service->release($second);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>15,'reserved_stock'=>0]);
        $this->assertSame(13,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_allocation_movements',4);
    }
    public function test_unverified_physical_coverage_rolls_back_available_deduction():void
    {
        $variant=Product::create(['title'=>'Unknown','handle'=>'unknown','base_price'=>100])->variants()->create(['sku'=>'UNKNOWN','title'=>'One','price'=>100,'stock'=>18]);
        app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>true]);
        try{app(StockAllocationService::class)->reserve('order',1,'item:1',$variant->id,5);$this->fail('Unknown stock accepted');}
        catch(\RuntimeException $error){$this->assertStringContainsString('ATP',$error->getMessage());}
        $this->assertSame(18,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('stock_allocations',0);
        $this->assertDatabaseCount('inventory_movements',0);
    }
    public function test_releasing_reservation_only_restores_actual_atp_after_a_shortfall():void
    {
        $variant=Product::create(['title'=>'Shortfall','handle'=>'shortfall','base_price'=>100])->variants()->create(['sku'=>'SHORTFALL','title'=>'One','price'=>100,'stock'=>8]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>10,'reserved_stock'=>0,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>true]);
        $service=app(StockAllocationService::class);
        $id=$service->reserve('order',1,'item:1',$variant->id,5);
        // A reconciled shortage leaves physical 4, reserved 5 and safety 2: ATP zero.
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>4]);
        $variant->update(['stock'=>0]);
        $service->release($id);$service->release($id);
        $this->assertSame(2,(int)$variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>4,'reserved_stock'=>0]);
        $this->assertDatabaseHas('stock_allocation_events',['stock_allocation_id'=>$id,'event'=>'released','available_delta'=>2]);
    }
}
