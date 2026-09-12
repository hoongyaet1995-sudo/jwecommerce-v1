<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\InventoryModeSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryModeSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_changes_when_reviewed_stock_changes():void
    {
        $variant=Product::create(['title'=>'Mode snapshot','handle'=>'mode-snapshot','base_price'=>10])
            ->variants()->create(['sku'=>'MODE-SNAPSHOT','title'=>'One','price'=>10,'stock'=>0]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        $reader=new InventoryModeSnapshot;
        $first=$reader->capture();
        $this->assertSame($first,$reader->capture());
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'version'=>1,'counted_at'=>now()]);
        $this->assertNotSame($first['hash'],$reader->capture()['hash']);
        $second=$reader->capture();
        DB::table('product_variants')->where('id',$variant->id)->update(['stock_version'=>1]);
        $this->assertNotSame($second['hash'],$reader->capture()['hash']);
    }
}
