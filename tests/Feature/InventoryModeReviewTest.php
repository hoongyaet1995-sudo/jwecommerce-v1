<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\InventoryModeReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryModeReviewTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();config(['marketplace.advanced_wms_enabled'=>true]);}

    private function fixture():array
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Mode review','handle'=>'mode-review','base_price'=>10])->variants()
            ->create(['sku'=>'MODE-REVIEW','title'=>'One','price'=>10,'stock'=>0,'enabled'=>true]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        return [$actor,$balance];
    }

    public function test_review_persists_server_snapshot_without_switching_mode_or_stock():void
    {
        [$actor,$balance]=$this->fixture();
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'reserved_stock'=>0,
            'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        $result=app(InventoryModeReviewService::class)->review('shared_pool','primary_all','Reviewed test stock',[],$actor);
        $this->assertSame(['MODE-REVIEW'=>['shared'=>20]],$result['allocation_plan']);
        $this->assertDatabaseHas('inventory_mode_reviews',['id'=>$result['review_id'],'actor_id'=>$actor->id,'committed_at'=>null]);
        $this->assertNull(DB::table('inventory_configuration')->where('id',1)->value('operating_mode'));
        $this->assertSame(20,(int)DB::table('inventory_balances')->where('id',$balance['balance_id'])->value('physical_stock'));
    }

    public function test_uncounted_balances_do_not_create_review():void
    {
        [$actor]=$this->fixture();
        try{app(InventoryModeReviewService::class)->review('shared_pool','primary_all','Review',[],$actor);$this->fail('Unknown stock accepted.');}
        catch(ValidationException $error){$this->assertArrayHasKey('mode',$error->errors());}
        $this->assertDatabaseCount('inventory_mode_reviews',0);
    }

    public function test_reservation_drift_cannot_enter_a_mode_review():void
    {
        [$actor,$balance]=$this->fixture();
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update([
            'physical_stock'=>20,'reserved_stock'=>3,'safety_stock'=>2,
            'counted_at'=>now(),'obligations_reconciled_at'=>now(),
        ]);
        try {
            app(InventoryModeReviewService::class)->review('shared_pool','primary_all','Review',[],$actor);
            $this->fail('A stale reservation total was accepted.');
        } catch(ValidationException $error) {
            $this->assertStringContainsString('reservation',$error->getMessage());
        }
        $this->assertDatabaseCount('inventory_mode_reviews',0);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'reserved_stock'=>3]);
    }

    public function test_api_rejects_client_actor_and_uses_authenticated_actor():void
    {
        [$actor,$balance]=$this->fixture();
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'reserved_stock'=>0,
            'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        $payload=['target_mode'=>'shared_pool','strategy'=>'primary_all','reason'=>'Reviewed test count'];
        $this->actingAs($actor)->postJson('/admin/api/inventory/mode-reviews',$payload+['actor_id'=>999])->assertUnprocessable();
        $this->postJson('/admin/api/inventory/mode-reviews',$payload)->assertOk()->assertJsonPath('actor_id',$actor->id)
            ->assertJsonPath('allocation_plan.MODE-REVIEW.shared',20);
        $this->assertDatabaseCount('inventory_mode_reviews',1);
    }

    public function test_commit_guard_rejects_other_actor_expiry_and_stock_drift():void
    {
        [$actor,$balance]=$this->fixture();
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'reserved_stock'=>0,
            'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        $other=User::factory()->create(['is_admin'=>true]);
        $review=app(InventoryModeReviewService::class)->review('shared_pool','primary_all','Review',[],$actor);
        $guard=app(\App\Services\Inventory\InventoryModeReviewGuard::class);
        $this->assertSame($review['review_id'],DB::transaction(fn()=>$guard->check($review['review_id'],$actor))->id);
        try{DB::transaction(fn()=>$guard->check($review['review_id'],$other));$this->fail('Foreign review accepted.');}
        catch(ValidationException $error){$this->assertStringContainsString('employee',$error->getMessage());}
        $this->travel(16)->minutes();
        try{DB::transaction(fn()=>$guard->check($review['review_id'],$actor));$this->fail('Expired review accepted.');}
        catch(ValidationException $error){$this->assertStringContainsString('expired',$error->getMessage());}
        $this->travelBack();
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->increment('version');
        try{DB::transaction(fn()=>$guard->check($review['review_id'],$actor));$this->fail('Stale review accepted.');}
        catch(ValidationException $error){$this->assertStringContainsString('changed',$error->getMessage());}
    }
}
