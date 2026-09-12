<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryBalanceProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_read_exposes_available_without_claiming_reconciled_orders():void
    {
        config(['marketplace.basic_wms_enabled'=>true]);
        config(['marketplace.read_operations_enabled'=>true]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $variant=Product::create(['title'=>'Basic read','handle'=>'basic-read','base_price'=>10])
            ->variants()->create(['sku'=>'BASIC-READ','title'=>'One','price'=>10,'stock'=>97]);
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update([
            'physical_stock'=>20,'safety_stock'=>2,'counted_at'=>now(),
        ]);
        $this->getJson('/admin/api/inventory/balances?variantId='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.basic_wms.available',18)
            ->assertJsonPath('data.0.basic_wms.order_coverage_complete',false)
            ->assertJsonPath('data.0.atp',null)->assertJsonPath('data.0.verified',false);
        $this->assertSame(97,(int)$variant->fresh()->stock);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        DB::table('product_variants')->where('id',$variant->id)->update(['data_classification'=>'test']);
        $this->getJson('/admin/api/marketplace/inventory')->assertOk()
            ->assertJsonPath('data.0.data_classification','test')
            ->assertJsonPath('data.0.wms_summary.physical_stock',20)
            ->assertJsonPath('data.0.wms_summary.available',18)
            ->assertJsonPath('data.0.wms_summary.safety_stock',2)
            ->assertJsonPath('data.0.wms_summary.balance_count',1)
            ->assertJsonPath('data.0.available_stock',97);
    }

    public function test_explicit_balance_configuration_is_idempotent_and_never_seeds_stock(): void
    {
        $url='/admin/api/inventory/balances';
        $this->postJson($url,[])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $variant=Product::create(['title'=>'Provision','handle'=>'provision','base_price'=>100])
            ->variants()->create(['sku'=>'PROVISION','title'=>'One','price'=>100,'stock'=>97]);
        $payload=['variant_id'=>$variant->id,'pool_kind'=>'shared'];
        $first=$this->postJson($url,$payload)->assertOk()->assertJsonPath('physical_stock',null)->assertJsonPath('atp',null)->json();
        $this->postJson($url,$payload)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('inventory_balances',1);
        $this->assertDatabaseCount('inventory_pools',1);
        $this->postJson($url,$payload+['physical_stock'=>100])->assertUnprocessable();
        $this->postJson($url,['variant_id'=>$variant->id,'pool_kind'=>'warehouse'])->assertUnprocessable();
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'CYB','name'=>'Cyberjaya']);
        $this->postJson($url,['variant_id'=>$variant->id,'pool_kind'=>'warehouse','warehouse_id'=>$warehouse])
            ->assertOk()->assertJsonPath('physical_stock',null);
        $this->assertDatabaseCount('inventory_balances',2);
        $this->assertSame(97,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertDatabaseCount('inventory_physical_movements',0);
        DB::table('inventory_balances')->where('id',$first['balance_id'])->update([
            'physical_stock'=>12,'reserved_stock'=>3,'safety_stock'=>2,'version'=>7,
        ]);
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('physical_stock',12)
            ->assertJsonPath('reserved_stock',3)->assertJsonPath('safety_stock',2)->assertJsonPath('version',7);
        $this->assertDatabaseCount('inventory_balances',2);
    }
}
