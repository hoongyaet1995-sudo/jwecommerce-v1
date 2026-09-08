<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTransferFlowTest extends TestCase
{
    use RefreshDatabase;
    private function fixture():array
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses']);
        $variant=Product::create(['title'=>'Transfer','handle'=>'transfer','base_price'=>100])->variants()->create(['sku'=>'TRANSFER','title'=>'One','price'=>100,'stock'=>97]);
        $ids=[];
        foreach(['SRC'=>20,'DST'=>5]as $code=>$physical){
            $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>$code,'name'=>$code,'status'=>'active','is_active'=>true]);
            $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'warehouse',$warehouse);
            DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>$physical,'reserved_stock'=>2,'safety_stock'=>1,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
            $ids[]=$balance['balance_id'];
        }
        return [$variant,...$ids];
    }
    public function test_dispatch_partial_receipts_and_retries_conserve_stock():void
    {
        [$variant,$source,$destination]=$this->fixture();
        $create=['source_balance_id'=>$source,'destination_balance_id'=>$destination,'quantity'=>8,'operation_key'=>'create','notes'=>'Move stock'];
        $transfer=$this->postJson('/admin/api/inventory/transfers',$create)->assertOk()->json('id');
        $this->getJson('/admin/api/inventory/transfer-options')->assertOk()->assertJsonCount(2,'data')->assertJsonPath('data.0.atp',17);
        $this->getJson('/admin/api/inventory/transfers')->assertOk()->assertJsonPath('data.0.sku','TRANSFER')->assertJsonPath('data.0.source_warehouse_name','SRC');
        $this->postJson('/admin/api/inventory/transfers',$create)->assertOk()->assertJsonPath('id',$transfer);
        $url='/admin/api/inventory/transfers/'.$transfer.'/transition';
        $dispatch=['action'=>'dispatch','quantity'=>0,'operation_key'=>'dispatch','version'=>0];
        $this->postJson($url,$dispatch)->assertOk()->assertJsonPath('in_transit_quantity',8);
        $this->postJson($url,$dispatch)->assertOk()->assertJsonPath('in_transit_quantity',8);
        $this->assertDatabaseHas('inventory_balances',['id'=>$source,'physical_stock'=>12,'reserved_stock'=>2]);
        $receive=['action'=>'receive','quantity'=>3,'operation_key'=>'receive-first','version'=>1];
        $this->postJson($url,$receive)->assertOk()->assertJsonPath('status','in_transit')->assertJsonPath('in_transit_quantity',5);
        $this->postJson($url,$receive)->assertOk()->assertJsonPath('received_quantity',3);
        $this->assertSame(25,(int)DB::table('inventory_balances')->sum('physical_stock')+5);
        $this->postJson($url,['action'=>'receive','quantity'=>6,'operation_key'=>'over','version'=>2])->assertUnprocessable();
        $this->postJson($url,['action'=>'cancel','quantity'=>0,'operation_key'=>'bad-cancel','version'=>2])->assertUnprocessable();
        $this->postJson($url,['action'=>'receive','quantity'=>5,'operation_key'=>'receive-final','version'=>2])->assertOk()->assertJsonPath('status','completed')->assertJsonPath('in_transit_quantity',0);
        $this->assertSame(25,(int)DB::table('inventory_balances')->sum('physical_stock'));
        $this->assertSame(97,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_transfer_events',3);
        $this->assertDatabaseCount('inventory_movements',0);
    }
    public function test_insufficient_atp_and_cancellation_do_not_change_stock():void
    {
        [,$source,$destination]=$this->fixture();
        $id=$this->postJson('/admin/api/inventory/transfers',['source_balance_id'=>$source,'destination_balance_id'=>$destination,'quantity'=>18,'operation_key'=>'large','notes'=>'Too much'])->assertOk()->json('id');
        $url='/admin/api/inventory/transfers/'.$id.'/transition';
        $this->postJson($url,['action'=>'dispatch','quantity'=>0,'operation_key'=>'dispatch','version'=>0])->assertUnprocessable();
        $this->postJson($url,['action'=>'cancel','quantity'=>0,'operation_key'=>'cancel','version'=>0])->assertOk()->assertJsonPath('status','cancelled');
        $this->assertDatabaseHas('inventory_balances',['id'=>$source,'physical_stock'=>20,'version'=>0]);
    }
    public function test_active_transfer_restores_only_destination_atp_gain():void
    {
        [$variant,$source,$destination]=$this->fixture();
        DB::table('inventory_configuration')->where('id',1)->update(['accounting_enabled'=>true]);
        DB::table('inventory_balances')->where('id',$destination)->update(['safety_stock'=>6]);
        $variant->update(['stock'=>17]);
        $id=$this->postJson('/admin/api/inventory/transfers',['source_balance_id'=>$source,'destination_balance_id'=>$destination,'quantity'=>8,'operation_key'=>'active','notes'=>'Move'])->assertOk()->json('id');
        $url='/admin/api/inventory/transfers/'.$id.'/transition';
        $this->postJson($url,['action'=>'dispatch','quantity'=>0,'operation_key'=>'dispatch','version'=>0])->assertOk();
        $this->assertSame(9,(int)$variant->fresh()->stock);
        $receive=['action'=>'receive','quantity'=>8,'operation_key'=>'receive','version'=>1];
        $this->postJson($url,$receive)->assertOk();
        $this->postJson($url,$receive)->assertOk();
        // Destination first fills its three-unit safety/reservation shortfall.
        $this->assertSame(14,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',2);
    }
    public function test_disabled_mode_and_unauthenticated_requests_are_rejected():void
    {
        $this->postJson('/admin/api/inventory/transfers',[])->assertUnauthorized();
        [,$source,$destination]=$this->fixture();
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>null]);
        $this->postJson('/admin/api/inventory/transfers',['source_balance_id'=>$source,'destination_balance_id'=>$destination,'quantity'=>1,'operation_key'=>'blocked','notes'=>'Unconfigured'])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_transfers',0);
    }
}
