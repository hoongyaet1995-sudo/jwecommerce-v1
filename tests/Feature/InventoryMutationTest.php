<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\InventoryMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InventoryMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_allocation_records_shared_movements_once(): void
    {
        $p=Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $v=$p->variants()->create(['sku'=>'TEST','title'=>'One','price'=>10,'stock'=>10]);
        $allocations=app(\App\Services\Inventory\StockAllocationService::class);
        $id=$allocations->reserve('marketplace_order',1,'12:0',$v->id,3);
        $allocations->release($id);
        $allocations->release($id);
        $this->assertDatabaseCount('inventory_movements',2);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]))
            ->from('/admin/products')->delete('/admin/products/'.$p->id)
            ->assertRedirect('/admin/products')->assertSessionHasErrors('product');
        $this->assertDatabaseHas('products',['id'=>$p->id]);
        $this->assertSame(10,(int)$v->fresh()->stock);
        $this->assertSame(2,(int)$v->fresh()->stock_version);
    }

    public function test_delta_replay_is_idempotent_and_absolute_edit_requires_current_version(): void
    {
        $p=Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $v=$p->variants()->create(['sku'=>'TEST','title'=>'One','price'=>10,'stock'=>10]);
        $service=app(InventoryMutationService::class);
        $service->change($v->id,-3,'order:1:reserve','Checkout reservation');
        $service->change($v->id,-3,'order:1:reserve','Checkout reservation');
        $this->assertSame(7,(int)$v->fresh()->stock);
        $this->assertSame(1,(int)$v->fresh()->stock_version);
        $this->assertDatabaseCount('inventory_movements',1);
        try { $service->setAvailable($v->id,10,0,'edit:1','Count review'); $this->fail('Stale edit accepted'); }
        catch (RuntimeException $e) { $this->assertSame('Inventory changed. Reload before adjusting quantity.',$e->getMessage()); }
        $service->setAvailable($v->id,9,1,'edit:2','Count review');
        $this->assertSame(9,(int)$v->fresh()->stock);
        try { $service->change($v->id,-4,'order:1:reserve','Checkout reservation'); $this->fail('Conflicting replay accepted'); }
        catch (RuntimeException $e) { $this->assertSame('Inventory operation identity conflicts with an earlier change.',$e->getMessage()); }
        $this->assertDatabaseCount('inventory_movements',2);
    }

    public function test_failed_or_rolled_back_change_leaves_no_stock_or_audit_effect(): void
    {
        $p=Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $v=$p->variants()->create(['sku'=>'TEST','title'=>'One','price'=>10,'stock'=>2]);
        $service=app(InventoryMutationService::class);
        try { $service->change($v->id,-3,'order:1','Reserve'); $this->fail('Negative availability accepted'); }
        catch (RuntimeException) {}
        DB::beginTransaction();
        $service->change($v->id,-1,'order:2','Reserve');
        DB::rollBack();
        $this->assertSame(2,(int)$v->fresh()->stock);
        $this->assertSame(0,(int)$v->fresh()->stock_version);
        $this->assertDatabaseCount('inventory_movements',0);
    }
}
