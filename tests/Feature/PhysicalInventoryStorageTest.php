<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\PhysicalBalanceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhysicalInventoryStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_baseline_does_not_inherit_legacy_availability(): void
    {
        $variant = Product::create(['title'=>'Physical','handle'=>'physical','base_price'=>100])
            ->variants()->create(['sku'=>'PHYSICAL','title'=>'One','price'=>100,'stock'=>97]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key'=>'shared','kind'=>'shared']);
        DB::table('inventory_balances')->insert(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id]);

        $balance = app(PhysicalBalanceReader::class)->read($pool, $variant->id);
        $this->assertNull($balance['physical_stock']);
        $this->assertNull($balance['reserved_stock']);
        $this->assertNull($balance['atp']);
        $this->assertFalse($balance['verified']);
        $this->assertSame(97, (int) $variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_warehouse_balances_are_independent_and_unreconciled_reservations_block_atp(): void
    {
        $variant = Product::create(['title'=>'Warehouses','handle'=>'warehouses','base_price'=>100])
            ->variants()->create(['sku'=>'WAREHOUSES','title'=>'One','price'=>100,'stock'=>97]);
        $reader = app(PhysicalBalanceReader::class);
        foreach (['CYB'=>[15,1,3,11], 'PEN'=>[10,0,2,8]] as $code=>$quantities) {
            $warehouse = DB::table('inventory_warehouses')->insertGetId(['code'=>$code,'name'=>$code]);
            $pool = DB::table('inventory_pools')->insertGetId(['pool_key'=>'warehouse:'.$warehouse,'kind'=>'warehouse','warehouse_id'=>$warehouse]);
            DB::table('inventory_balances')->insert(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id,
                'physical_stock'=>$quantities[0], 'reserved_stock'=>$quantities[1], 'safety_stock'=>$quantities[2],
                'counted_at'=>now(), 'obligations_reconciled_at'=>null]);
            $this->assertNull($reader->read($pool,$variant->id)['atp']);
            DB::table('inventory_balances')->where('inventory_pool_id',$pool)->update(['obligations_reconciled_at'=>now()]);
            $this->assertSame($quantities[3],$reader->read($pool,$variant->id)['atp']);
        }
        $this->assertSame(97,(int)$variant->fresh()->stock);
    }

    public function test_deficit_is_visible_and_missing_balance_is_not_zero(): void
    {
        $variant = Product::create(['title'=>'Deficit','handle'=>'deficit','base_price'=>100])
            ->variants()->create(['sku'=>'DEFICIT','title'=>'One','price'=>100,'stock'=>0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key'=>'shared','kind'=>'shared']);
        $reader = app(PhysicalBalanceReader::class);
        $this->assertNull($reader->read($pool,$variant->id));
        DB::table('inventory_balances')->insert(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id,
            'physical_stock'=>2,'reserved_stock'=>4,'safety_stock'=>1,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        $balance = $reader->read($pool,$variant->id);
        $this->assertSame(0,$balance['atp']);
        $this->assertSame(3,$balance['shortfall']);
    }
}
