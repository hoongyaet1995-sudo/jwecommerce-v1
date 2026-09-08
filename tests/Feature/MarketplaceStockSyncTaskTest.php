<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Marketplace\MarketplaceStockSyncTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceStockSyncTaskTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Sync fixture','handle'=>'sync-fixture','base_price'=>100])
            ->variants()->create(['sku'=>'SYNC-1','title'=>'One','price'=>100,'stock'=>8]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null)['balance_id'];
        DB::table('inventory_balances')->where('id',$balance)->update([
            'physical_stock'=>12,'reserved_stock'=>1,'safety_stock'=>3,'counted_at'=>now(),'obligations_reconciled_at'=>now(),
        ]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool','accounting_enabled'=>true]);
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'SYNC-WH','name'=>'Sync warehouse','status'=>'active','is_active'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','region'=>'MY','external_shop_id'=>'2001','shop_name'=>'Lazada MY','status'=>'connected']);
        DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,
            'platform_location_id'=>'LZ-WH-1','is_enabled'=>true,'version'=>4,'created_at'=>now(),'updated_at'=>now()]);
        $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'I-1','external_variant_id'=>'S-1',
            'observed_data'=>json_encode(['sku'=>'SYNC-1']),'fetched_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id,'actor_id'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
        return compact('actor','store','balance','listing');
    }

    public function test_verified_atp_stages_an_immutable_non_executable_store_stock_task(): void
    {
        $f=$this->fixture();
        $service=app(MarketplaceStockSyncTaskService::class);
        $receipt=$service->stage($f['store'],$f['balance'],'lazada-sync-1',$f['actor']);
        $this->assertSame('staged',$receipt['status']);
        $this->assertSame(8,$receipt['desired_quantity']);
        $this->assertSame('LZ-WH-1',$receipt['platform_location_id']);
        $this->assertSame($receipt,$service->stage($f['store'],$f['balance'],'lazada-sync-1',$f['actor']));
        $this->assertDatabaseHas('marketplace_stock_sync_tasks',['operation_key'=>'lazada-sync-1','status'=>'staged','desired_quantity'=>8]);
        $task=DB::table('marketplace_stock_sync_tasks')->first();
        $this->assertSame(4,(int)$task->route_version);
        $this->assertNull($task->dispatched_at);
        $this->assertNull($task->confirmed_at);
    }

    public function test_staging_rejects_an_unverified_balance_or_route_without_creating_a_task(): void
    {
        $f=$this->fixture();
        DB::table('inventory_balances')->where('id',$f['balance'])->update(['obligations_reconciled_at'=>null]);
        try { app(MarketplaceStockSyncTaskService::class)->stage($f['store'],$f['balance'],'blocked-sync',$f['actor']); $this->fail('Unverified ATP accepted.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('verified',$e->getMessage()); }
        $this->assertDatabaseCount('marketplace_stock_sync_tasks',0);
        DB::table('inventory_balances')->where('id',$f['balance'])->update(['obligations_reconciled_at'=>now()]);
        DB::table('inventory_fulfillment_routes')->where('channel_key','marketplace:'.$f['store'])->update(['is_enabled'=>false]);
        try { app(MarketplaceStockSyncTaskService::class)->stage($f['store'],$f['balance'],'blocked-route',$f['actor']); $this->fail('Disabled route accepted.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('fulfillment route',$e->getMessage()); }
        $this->assertDatabaseCount('marketplace_stock_sync_tasks',0);
    }
    public function test_api_uses_the_session_actor_and_rejects_extra_fields(): void
    {
        $f=$this->fixture();
        $url='/admin/api/marketplace/stores/'.$f['store'].'/stock-sync-tasks';
        $this->postJson($url,['inventory_balance_id'=>$f['balance'],'operation_key'=>'api-sync'])->assertUnauthorized();
        $this->actingAs($f['actor'])->postJson($url,['inventory_balance_id'=>$f['balance'],'operation_key'=>'api-sync','actor_id'=>999])->assertUnprocessable();
        $this->postJson($url,['inventory_balance_id'=>$f['balance'],'operation_key'=>'api-sync'])->assertOk()
            ->assertJsonPath('status','staged')->assertJsonPath('desired_quantity',8);
        $this->assertDatabaseHas('marketplace_stock_sync_tasks',['operation_key'=>'api-sync','requested_by'=>$f['actor']->id]);
    }
    public function test_review_marks_a_staged_task_stale_when_its_wms_balance_changes(): void
    {
        $f=$this->fixture();
        $task=app(MarketplaceStockSyncTaskService::class)->stage($f['store'],$f['balance'],'review-sync',$f['actor']);
        $this->actingAs($f['actor'])->getJson('/admin/api/marketplace/stock-sync-tasks/'.$task['id'])->assertOk()
            ->assertJsonPath('current',true)->assertJsonCount(0,'blockers');
        DB::table('inventory_balances')->where('id',$f['balance'])->increment('version');
        $this->getJson('/admin/api/marketplace/stock-sync-tasks/'.$task['id'])->assertOk()
            ->assertJsonPath('current',false)->assertJsonFragment(['balance_changed']);
        $this->assertDatabaseHas('marketplace_stock_sync_tasks',['id'=>$task['id'],'status'=>'staged']);
    }
}
