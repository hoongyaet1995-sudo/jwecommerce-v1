<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhysicalBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_balance_is_empty_and_real_warehouse_balance_preserves_unknowns(): void
    {
        $variant=Product::create(['title'=>'Balance','handle'=>'balance','base_price'=>100])
            ->variants()->create(['sku'=>'BALANCE','title'=>'One','price'=>100,'stock'=>97]);
        $url='/admin/api/inventory/balances?variantId='.$variant->id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertOk()
            ->assertJsonPath('total',0)->assertJsonCount(0,'data');
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'CYB','name'=>'Cyberjaya']);
        $pool=DB::table('inventory_pools')->insertGetId(['pool_key'=>'warehouse:'.$warehouse,'kind'=>'warehouse','warehouse_id'=>$warehouse]);
        $balance=DB::table('inventory_balances')->insertGetId(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.balance_id',$balance)
            ->assertJsonPath('data.0.warehouse_id',$warehouse)->assertJsonPath('data.0.warehouse_name','Cyberjaya')
            ->assertJsonPath('data.0.physical_stock',null)->assertJsonPath('data.0.atp',null)
            ->assertJsonPath('data.0.pool_kind','warehouse')->assertJsonPath('data.0.version',0);
        $this->assertSame(97,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_count_reviews',0);
        $this->getJson('/admin/api/inventory/balances?variantId=999999999')->assertNotFound();
        $this->getJson('/admin/api/inventory/balances')->assertUnprocessable();
    }
}
