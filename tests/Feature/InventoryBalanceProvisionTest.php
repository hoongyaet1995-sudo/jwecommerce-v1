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
