<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\ManagedInventoryActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManagedInventoryActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazada_managed_items_allow_partial_outbound_and_cancellation(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Lazada managed','handle'=>'lazada-managed','base_price'=>100])->variants()->create(['sku'=>'LZ-MANAGED','title'=>'One','price'=>100,'stock'=>0]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'LZ-WH','name'=>'Test warehouse','status'=>'active','is_active'=>true]);
        DB::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$warehouse,'platform_location_id'=>'MY-WH','is_enabled'=>true]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>10,'safety_stock'=>2,'counted_at'=>now()]);
        app(ManagedInventoryActivation::class)->activate('marketplace:'.$store,$balance['balance_id'],$actor,'New orders');
        $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'100','external_variant_id'=>'200','observed_data'=>'{}','fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
        $this->travel(2)->seconds();
        $sync=app(\App\Services\Marketplace\MarketplaceStockSyncTaskService::class);
        $staged=$sync->stage($store,$balance['balance_id'],'managed:before',$actor);
        $this->assertSame(8,$staged['desired_quantity']);
        $line=['order_id'=>'300','product_id'=>'100','sku_id'=>'200','warehouse_code'=>'MY-WH','is_fbl'=>0,'semi_managed'=>false,'status'=>'pending'];
        $payload=['order_id'=>'300','created_at'=>now()->format('Y-m-d H:i:s O'),'order_items'=>[$line+['order_item_id'=>'301'],$line+['order_item_id'=>'302']]];
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'300','platform_status'=>'pending','platform_updated_at'=>now()->timestamp,'encrypted_payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
        $service=app(\App\Services\Marketplace\MarketplaceOrderInventoryService::class);
        $service->process($order);$service->process($order);
        $this->assertDatabaseCount('stock_allocations',2);
        $this->assertFalse($sync->review($staged['id'])['current']);
        $latest=$sync->stage($store,$balance['balance_id'],'managed:after',$actor);
        $this->assertSame(6,$latest['desired_quantity']);
        $this->assertTrue($sync->review($latest['id'])['current']);
        $sweep=app(\App\Services\Marketplace\ManagedInventoryStockSweep::class);
        $this->assertSame(1,$sweep->run()['staged']);
        $sweep->run();
        $this->assertDatabaseCount('marketplace_stock_sync_tasks',3);
        config(['marketplace.stock_sync_operations_enabled'=>true,'marketplace.sandbox_stock_writes_enabled'=>false]);
        try {
            app(\App\Services\Marketplace\MarketplaceStockSyncDispatchService::class)->dispatch($latest['id']);
            $this->fail('Managed sync bypassed sandbox write gate');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403,$error->getStatusCode());
        }
        $payload['order_items'][0]['status']='shipped';$payload['order_items'][1]['status']='canceled';
        DB::table('marketplace_orders')->where('id',$order)->update(['encrypted_payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)),'platform_updated_at'=>now()->addSecond()->timestamp]);
        $service->process($order);$service->process($order);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>9]);
        $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$order,'line_key'=>'301','status'=>'consumed']);
        $this->assertDatabaseHas('marketplace_inventory_items',['order_id'=>$order,'line_key'=>'302','status'=>'released']);
        $this->assertDatabaseCount('inventory_allocation_movements',4);
        $this->assertSame(0,(int)$variant->fresh()->stock);
    }

    public function test_shopee_explicit_activation_reserves_without_global_legacy_accounting(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Shopee managed','handle'=>'shopee-managed','base_price'=>100])->variants()->create(['sku'=>'SH-MANAGED','title'=>'One','price'=>100,'stock'=>0]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>10,'safety_stock'=>2,'counted_at'=>now()]);
        app(ManagedInventoryActivation::class)->activate('marketplace:'.$store,$balance['balance_id'],$actor,'New orders');
        $listing=DB::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'100','external_variant_id'=>'200','observed_data'=>'{}','fetched_at'=>now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
        $this->travel(2)->seconds();
        $payload=['create_time'=>now()->timestamp,'fulfillment_flag'=>'fulfilled_by_local_seller','item_list'=>[['item_id'=>100,'model_id'=>200,'model_quantity_purchased'=>3]]];
        $order=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'QA-SHOPEE','platform_status'=>'READY_TO_SHIP','platform_updated_at'=>now()->timestamp,'encrypted_payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
        $service=app(\App\Services\Marketplace\MarketplaceOrderInventoryService::class);
        $service->process($order);$service->process($order);
        $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$order,'status'=>'reserved']);
        $this->assertDatabaseCount('stock_allocations',1);
        $this->assertSame(0,(int)$variant->fresh()->stock);
        DB::table('marketplace_orders')->where('id',$order)->update(['platform_status'=>'SHIPPED']);
        $service->process($order);$service->process($order);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>7]);
        $this->assertDatabaseCount('inventory_allocation_movements',2);
        DB::table('marketplace_orders')->where('id',$order)->update(['platform_status'=>'READY_TO_SHIP']);
        $service->process($order);
        $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$order,'error_code'=>'inventory_lifecycle_conflict']);
        $payload['create_time']=now()->subDay()->timestamp;
        $historical=DB::table('marketplace_orders')->insertGetId(['store_id'=>$store,'external_order_id'=>'QA-OLD','platform_status'=>'CANCELLED','platform_updated_at'=>now()->timestamp,'encrypted_payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload)),'fetched_at'=>now()]);
        $service->process($historical);
        $this->assertDatabaseHas('marketplace_order_inventory',['order_id'=>$historical,'error_code'=>'historical_order']);
    }

    public function test_paid_order_uses_saved_ownership_and_keeps_payment_truth_on_shortage(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Paid','handle'=>'paid','base_price'=>100])->variants()->create(['sku'=>'PAID','title'=>'One','price'=>100,'stock'=>0]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>5,'safety_stock'=>0,'counted_at'=>now()]);
        $activation=app(ManagedInventoryActivation::class)->activate('website',$balance['balance_id'],$actor,'New orders');
        $order=\App\Models\Order::create(['number'=>'PAID-WMS','email'=>'qa@example.test','status'=>'paid','subtotal'=>600,'total'=>600]);
        $item=$order->items()->create(['product_variant_id'=>$variant->id,'sku'=>'PAID','title'=>'Paid','unit_price'=>100,'quantity'=>6,'line_total'=>600]);
        DB::table('order_items')->where('id',$item->id)->update(['inventory_order_activation_id'=>$activation['id']]);
        $service=app(\App\Services\Inventory\OrderAllocationService::class);
        $service->reservePaid($order);
        $this->assertSame('paid',$order->fresh()->status);
        $this->assertSame('managed_stock_unavailable',$order->fresh()->inventory_error);
        $this->assertDatabaseCount('stock_allocations',0);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>10]);
        $service->reservePaid($order);$service->reservePaid($order);
        $this->assertNull($order->fresh()->inventory_error);
        $this->assertDatabaseCount('stock_allocations',1);
        $service->consume($order);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>4]);
        $this->assertSame(0,(int)$variant->fresh()->stock);
    }

    public function test_managed_reservation_consumption_and_release_never_mutate_legacy_stock(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Managed flow','handle'=>'managed-flow','base_price'=>100])->variants()->create(['sku'=>'MANAGED-FLOW','title'=>'One','price'=>100,'stock'=>0]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'safety_stock'=>2,'counted_at'=>now()]);
        $activation=app(ManagedInventoryActivation::class)->activate('website',$balance['balance_id'],$actor,'New managed orders');
        $service=app(\App\Services\Inventory\StockAllocationService::class);
        $first=$service->reserve('order',1,'item:1',$variant->id,5,$activation['id']);
        $this->assertSame($first,$service->reserve('order',1,'item:1',$variant->id,5,$activation['id']));
        $second=$service->reserve('order',2,'item:2',$variant->id,3,$activation['id']);
        $service->consume($first);$service->consume($first);
        $service->release($second);$service->release($second);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>15,'reserved_stock'=>null,'obligations_reconciled_at'=>null]);
        $this->assertDatabaseHas('stock_allocation_events',['stock_allocation_id'=>$second,'event'=>'released','available_after'=>13]);
        $this->assertSame(0,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',0);
        $this->assertDatabaseCount('inventory_allocation_movements',4);
        $third=$service->reserve('order',3,'item:3',$variant->id,5,$activation['id']);
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>4]);
        $service->release($third);
        $this->assertDatabaseHas('stock_allocation_events',['stock_allocation_id'=>$third,'event'=>'released','available_delta'=>2,'available_after'=>2]);
        try {
            $service->reserve('order',4,'item:4',$variant->id,3,$activation['id']);
            $this->fail('Insufficient ATP accepted');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('ATP',$error->getMessage());
        }
        $this->assertDatabaseCount('stock_allocations',3);
        $this->assertSame(0,(int)$variant->fresh()->stock);
    }

    public function test_explicit_activation_preserves_unknown_obligations_and_excludes_older_orders(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Managed','handle'=>'managed','base_price'=>100])->variants()->create(['sku'=>'MANAGED','title'=>'One','price'=>100,'stock'=>77]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        $service=app(ManagedInventoryActivation::class);
        try {
            $service->activate('website',$balance['balance_id'],$actor,'Begin new orders');
            $this->fail('Unknown shelf inventory was accepted');
        } catch (ValidationException $error) {
            $this->assertDatabaseCount('inventory_order_activations',0);
        }
        DB::table('inventory_balances')->where('id',$balance['balance_id'])->update(['physical_stock'=>20,'safety_stock'=>2,'counted_at'=>now()]);
        $this->travelTo(now()->startOfSecond());
        $activation=$service->activate('website',$balance['balance_id'],$actor,'Begin new orders');
        $this->actingAs($actor)->postJson('/admin/api/inventory/order-activations',[
            'channel_key'=>'website','balance_id'=>$balance['balance_id'],'reason'=>'Review approved','orders_created_after'=>'2000-01-01',
        ])->assertUnprocessable();
        $this->postJson('/admin/api/inventory/order-activations',[
            'channel_key'=>'website','balance_id'=>$balance['balance_id'],'reason'=>'Review approved',
        ])->assertOk()->assertJsonPath('data.id',$activation['id'])->assertJsonPath('data.actor_id',$actor->id);
        $this->assertNull($service->eligible('website',$variant->id,now()->subSecond()));
        $this->assertNull($service->eligible('website',$variant->id,now()));
        $this->assertSame($activation['id'],$service->eligible('website',$variant->id,now()->addSecond())['id']);
        $this->assertNull($service->eligible('marketplace:999',$variant->id,now()->addSecond()));
        $this->assertSame($activation['id'],$service->activate('website',$balance['balance_id'],$actor,'Retry')['id']);
        $this->assertDatabaseCount('inventory_order_activations',1);
        $this->assertDatabaseHas('inventory_balances',['id'=>$balance['balance_id'],'physical_stock'=>20,'reserved_stock'=>null,'obligations_reconciled_at'=>null]);
        $this->assertSame(77,(int)$variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_configuration',['id'=>1,'accounting_enabled'=>false]);
    }
}
