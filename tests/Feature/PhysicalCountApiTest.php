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
    protected function setUp():void
    {
        parent::setUp();
        config(['marketplace.basic_wms_enabled'=>true]);
    }

    private const URL = '/admin/api/inventory/count-reviews';
    public function test_basic_review_reports_available_without_claiming_order_coverage():void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($actor);
        $variant=Product::create(['title'=>'Basic review','handle'=>'basic-review','base_price'=>10])->variants()
            ->create(['sku'=>'BASIC-REVIEW','title'=>'One','price'=>10,'stock'=>97]);
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
        DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>20,'counted_at'=>now(),'reserved_stock'=>9,'safety_stock'=>2]);
        $this->postJson('/admin/api/inventory/adjustment-reviews',[
            'balance_id'=>$balance,'quantity_delta'=>5,'reason'=>'Basic review only',
        ])->assertCreated()->assertJsonPath('atp',null)
            ->assertJsonPath('basic_wms.available',23)
            ->assertJsonPath('basic_wms.owned_reserved',0)
            ->assertJsonPath('basic_wms.order_coverage_complete',false)
            ->assertJsonPath('basic_wms.platform_write_authorized',false);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>20]);
        $this->assertSame(97,(int)$variant->fresh()->stock);
    }
    public function test_manual_inbound_outbound_and_adjustment_preserve_legacy_stock_and_receipt_time():void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($actor);
        $variant=Product::create(['title'=>'Manual ledger','handle'=>'manual-ledger','base_price'=>10])->variants()
            ->create(['sku'=>'MANUAL-LEDGER','title'=>'One','price'=>10,'stock'=>97]);
        $balance=app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
        $count=$this->postJson(self::URL,['balance_id'=>$balance,'physical_count'=>20,'reason'=>'Opening test count'])->assertCreated()->json('id');
        $this->postJson(self::URL.'/'.$count.'/confirm',['acknowledged'=>true])->assertOk();
        foreach([[5,'Product inbound'],[-3,'Product outbound'],[-1,'Manual adjustment']] as [$delta,$reason]){
            $review=$this->postJson('/admin/api/inventory/adjustment-reviews',[
                'balance_id'=>$balance,'quantity_delta'=>$delta,'reason'=>$reason,
            ])->assertCreated()->json('id');
            $receipt=$this->postJson(self::URL.'/'.$review.'/confirm',['acknowledged'=>true])->assertOk()
                ->assertJsonPath('actor_id',$actor->id)->assertJsonPath('reason',$reason)->json();
            $this->assertNotEmpty($receipt['created_at']??null);
            $this->travel(1)->minutes();
            $this->postJson(self::URL.'/'.$review.'/confirm',['acknowledged'=>true])->assertOk()->assertExactJson($receipt);
        }
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance,'physical_stock'=>21]);
        $this->assertDatabaseCount('inventory_physical_movements',4);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertDatabaseCount('stock_allocations',0);
        $this->assertSame(97,(int)$variant->fresh()->stock);
    }
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
