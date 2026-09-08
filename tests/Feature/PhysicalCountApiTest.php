<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhysicalCountApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/api/inventory/count-reviews';
    public function test_active_count_updates_atp_and_preserves_obligations():void
    {
        $actor=User::factory()->create(['is_admin'=>true]);$this->actingAs($actor);
        $variant=Product::create(['title'=>'Active count','handle'=>'active-count','base_price'=>100])->variants()->create(['sku'=>'ACTIVE-COUNT','title'=>'One','price'=>100,'stock'=>6]);
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
        DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>10,'reserved_stock'=>2,'safety_stock'=>2,'counted_at'=>now(),'obligations_reconciled_at'=>now()]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>true]);
        $review=$this->postJson(self::URL,['balance_id'=>$balance,'physical_count'=>3,'reason'=>'Cycle count shortage'])->assertCreated()->assertJsonPath('atp',0)->json('id');
        $url=self::URL.'/'.$review.'/confirm';
        $this->postJson($url,['acknowledged'=>true])->assertOk();
        $this->postJson($url,['acknowledged'=>true])->assertOk();
        $this->assertSame(0,(int)$variant->fresh()->stock);
        $this->assertNotNull(DB::table('inventory_balances')->where('id',$balance)->value('obligations_reconciled_at'));
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>3,'reserved_stock'=>2,'safety_stock'=>2]);
        $this->assertDatabaseCount('inventory_movements',1);
    }
    public function test_delta_review_rejects_unknown_stock_and_overdraw_and_confirms_once():void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $variant=Product::create(['title'=>'Delta','handle'=>'delta','base_price'=>100])->variants()->create(['sku'=>'DELTA','title'=>'One','price'=>100,'stock'=>97]);
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
        $payload=['balance_id'=>$balance,'quantity_delta'=>-3,'reason'=>'Damaged write-off'];
        $this->postJson('/admin/api/inventory/adjustment-reviews',$payload)->assertUnprocessable();
        DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>5,'counted_at'=>'2026-09-01 00:00:00']);
        $this->postJson('/admin/api/inventory/adjustment-reviews',array_replace($payload,['quantity_delta'=>-6]))->assertUnprocessable();
        $review=$this->postJson('/admin/api/inventory/adjustment-reviews',$payload)->assertCreated()->assertJsonPath('physical_count',2)->json('id');
        $url=self::URL.'/'.$review.'/confirm';
        $this->postJson($url,['acknowledged'=>true])->assertOk()->assertJsonPath('physical_delta',-3);
        $this->postJson($url,['acknowledged'=>true])->assertOk();
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>2,'counted_at'=>'2026-09-01 00:00:00']);
        $this->assertDatabaseCount('inventory_physical_movements',1);
        $this->assertSame(97,(int)$variant->fresh()->stock);
    }

    public function test_count_requires_admin_and_uses_session_actor_with_idempotent_confirmation(): void
    {
        $this->postJson(self::URL,[])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->postJson(self::URL,[])->assertUnauthorized();
        $actor = User::factory()->create(['is_admin'=>true]);
        $variant = Product::create(['title'=>'API count','handle'=>'api-count','base_price'=>100])
            ->variants()->create(['sku'=>'API-COUNT','title'=>'One','price'=>100,'stock'=>97]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key'=>'shared','kind'=>'shared']);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id]);
        $payload = ['balance_id'=>$balance,'physical_count'=>0,'reason'=>'Empty shelf'];
        $this->actingAs($actor)->postJson(self::URL,$payload+['actor_id'=>999])->assertUnprocessable();
        foreach (['',1.5,-1,2147483648] as $invalid) {
            $this->postJson(self::URL,array_replace($payload,['physical_count'=>$invalid]))->assertUnprocessable();
        }
        $review = $this->postJson(self::URL,$payload)->assertCreated()
            ->assertJsonPath('actor_id',$actor->id)->assertJsonPath('physical_count',0)
            ->assertJsonPath('atp',null)->json('id');
        $this->assertDatabaseCount('inventory_physical_movements',0);
        $confirm = self::URL.'/'.$review.'/confirm';
        $this->postJson($confirm,['acknowledged'=>false])->assertUnprocessable();
        $this->postJson($confirm,['acknowledged'=>true,'physical_count'=>99])->assertUnprocessable();
        $first = $this->postJson($confirm,['acknowledged'=>true])->assertOk()->assertJsonPath('physical_after',0)->json();
        $this->postJson($confirm,['acknowledged'=>true])->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('inventory_physical_movements',1);
        $this->assertSame(97,(int)$variant->fresh()->stock);
        $staleReview = $this->postJson(self::URL,array_replace($payload,['physical_count'=>10]))->assertCreated()->json('id');
        DB::table('product_variants')->where('id',$variant->id)->increment('stock_version');
        $this->postJson(self::URL.'/'.$staleReview.'/confirm',['acknowledged'=>true])
            ->assertUnprocessable()->assertJsonValidationErrors('inventory');
        $this->assertDatabaseCount('inventory_physical_movements',1);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>0,'version'=>1]);
    }
}
