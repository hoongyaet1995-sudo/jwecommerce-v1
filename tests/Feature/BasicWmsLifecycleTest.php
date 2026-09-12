<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BasicWmsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public static function modes(): array
    {
        return [['shared_pool', 'shared'], ['independent_warehouses', 'warehouse']];
    }

    #[DataProvider('modes')]
    public function test_initial_configuration_through_manual_operations_preserves_legacy_stock(string $mode, string $pool): void
    {
        config(['marketplace.basic_wms_enabled'=>true, 'marketplace.advanced_wms_enabled'=>false]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($actor);
        $variant=Product::create(['title'=>'Lifecycle fixture','handle'=>'lifecycle-fixture','base_price'=>1])
            ->variants()->create(['sku'=>'LIFECYCLE','title'=>'Test','price'=>1,'stock'=>17,'enabled'=>true]);

        $this->postJson('/admin/api/inventory/basic/configuration',[
            'mode'=>$mode,'version'=>0,'reason'=>'Initial configuration','operation_key'=>(string)Str::uuid(),
        ])->assertOk();
        $this->assertDatabaseCount('inventory_balances',0);
        $warehouse=$this->postJson('/admin/api/inventory/warehouses',[
            'code'=>'QA-LIFECYCLE','name'=>'Lifecycle warehouse','status'=>'active','is_default_master'=>true,
        ])->assertCreated()->json('id');
        $balance=$this->postJson('/admin/api/inventory/balances',[
            'variant_id'=>$variant->id,'pool_kind'=>$pool,
        ]+($pool==='warehouse'?['warehouse_id'=>$warehouse]:[]))->assertOk()->json('balance_id');
        $this->getJson('/admin/api/inventory/balances?variantId='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.basic_wms.available',null);
        $this->postJson('/admin/api/inventory/adjustment-reviews',[
            'balance_id'=>$balance,'quantity_delta'=>5,'reason'=>'Cannot infer initial stock',
        ])->assertUnprocessable();

        $review=$this->postJson('/admin/api/inventory/count-reviews',[
            'balance_id'=>$balance,'physical_count'=>20,'reason'=>'Count physical shelves',
        ])->assertCreated()->assertJsonPath('physical_before',null)->json('id');
        $this->confirm($review)->assertOk()->assertJsonPath('physical_after',20);
        $this->confirm($review)->assertOk()->assertJsonPath('physical_after',20);
        $this->postJson('/admin/api/inventory/basic/safety',[
            'balance_id'=>$balance,'balance_version'=>1,'safety_stock'=>2,
            'reason'=>'Initial safety','operation_key'=>(string)Str::uuid(),
        ])->assertOk()->assertJsonPath('basic_wms.available',18);

        foreach ([[5,25,'Inbound'],[-3,22,'Outbound'],[-1,21,'Manual correction']] as [$delta,$after,$reason]) {
            $review=$this->postJson('/admin/api/inventory/adjustment-reviews',[
                'balance_id'=>$balance,'quantity_delta'=>$delta,'reason'=>$reason,
            ])->assertCreated()->json('id');
            $this->confirm($review)->assertOk()->assertJsonPath('physical_after',$after);
        }
        $this->postJson('/admin/api/inventory/adjustment-reviews',[
            'balance_id'=>$balance,'quantity_delta'=>-22,'reason'=>'Overdraw rejected',
        ])->assertUnprocessable();
        $this->getJson('/admin/api/inventory/balances?variantId='.$variant->id)->assertOk()
            ->assertJsonPath('data.0.basic_wms.available',19)
            ->assertJsonPath('data.0.basic_wms.order_coverage_complete',false)
            ->assertJsonPath('data.0.basic_wms.platform_write_authorized',false);
        $this->assertSame(17,(int)$variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_configuration',['id'=>1,'operating_mode'=>$mode,'accounting_enabled'=>false]);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>21,'safety_stock'=>2,'version'=>5]);
        $this->assertDatabaseCount('inventory_physical_movements',4);
        $this->assertSame([$actor->id],DB::table('inventory_physical_movements')->distinct()->pluck('actor_id')->all());
        $this->assertDatabaseCount('stock_allocations',0);
    }

    private function confirm(string $review): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/admin/api/inventory/count-reviews/'.$review.'/confirm',['acknowledged'=>true]);
    }
}
