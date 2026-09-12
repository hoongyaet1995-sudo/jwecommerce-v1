<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReadinessTest extends TestCase
{
    use RefreshDatabase;
    public function test_a_balance_in_another_warehouse_does_not_cover_the_route():void
    {
        $db=\Illuminate\Support\Facades\DB::class;
        $db::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses']);
        $product=\App\Models\Product::create(['title'=>'Route','handle'=>'route-test','base_price'=>100,'status'=>'draft']);
        $variant=$product->variants()->create(['sku'=>'ROUTE','title'=>'Route','price'=>100,'stock'=>0]);
        $first=$db::table('inventory_warehouses')->insertGetId(['code'=>'A','name'=>'A','status'=>'active','is_active'=>true]);
        $second=$db::table('inventory_warehouses')->insertGetId(['code'=>'B','name'=>'B','status'=>'active','is_active'=>true]);
        $db::table('inventory_fulfillment_routes')->insert(['channel_key'=>'website','warehouse_id'=>$first,'is_enabled'=>true]);
        $provisioner=app(\App\Services\Inventory\InventoryBalanceProvisioner::class);
        $provisioner->provision($variant->id,'warehouse',$second);
        $service=app(\App\Services\Inventory\InventoryReadinessService::class);
        $this->assertSame(1,$service->inspect()['missing_routed_balances']);
        $provisioner->provision($variant->id,'warehouse',$first);
        $this->assertSame(0,$service->inspect()['missing_routed_balances']);
        $store=$db::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','status'=>'connected','region'=>'MY','environment'=>'live']);
        $listing=$db::table('marketplace_listing_variants')->insertGetId(['store_id'=>$store,'external_product_id'=>'1','external_variant_id'=>'2','observed_data'=>'{}','fetched_at'=>now()]);
        $db::table('marketplace_variant_mappings')->insert(['store_id'=>$store,'listing_variant_id'=>$listing,'product_variant_id'=>$variant->id]);
        $db::table('marketplace_inventory_policies')->insert(['store_id'=>$store,'enabled'=>true]);
        $third=$db::table('inventory_warehouses')->insertGetId(['code'=>'C','name'=>'C','status'=>'active','is_active'=>true]);
        $db::table('inventory_fulfillment_routes')->insert(['channel_key'=>'marketplace:'.$store,'warehouse_id'=>$third,'is_enabled'=>true]);
        $this->assertSame(1,$service->inspect()['missing_routed_balances']);
        $provisioner->provision($variant->id,'warehouse',$third);
        $this->assertSame(0,$service->inspect()['missing_routed_balances']);
    }
    public function test_existing_balance_does_not_hide_uncovered_variants():void
    {
        $db=\Illuminate\Support\Facades\DB::class;
        $db::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        $product=\App\Models\Product::create(['title'=>'Test','handle'=>'readiness-test','base_price'=>100,'status'=>'draft']);
        $first=$product->variants()->create(['sku'=>'COVERED','title'=>'Covered','price'=>100,'stock'=>0]);
        $product->variants()->create(['sku'=>'MISSING','title'=>'Missing','price'=>100,'stock'=>0]);
        app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($first->id,'shared',null);
        $result=app(\App\Services\Inventory\InventoryReadinessService::class)->inspect();
        $this->assertSame(1,$result['missing_variant_balances']);
        $this->assertContains('Some enabled variants have no balance for the selected operating mode.',$result['blockers']);
        $db::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses']);
        $result=app(\App\Services\Inventory\InventoryReadinessService::class)->inspect();
        $this->assertSame(1,$result['missing_required_routes']);
        $this->assertContains('Website or enabled marketplace accounting has no active fulfillment route.',$result['blockers']);
    }
    public function test_empty_configuration_is_not_presented_as_ready():void
    {
        $this->getJson('/admin/api/inventory/readiness')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson('/admin/api/inventory/readiness')
            ->assertOk()->assertJsonPath('operating_mode',null)->assertJsonPath('activation_ready',false)
            ->assertJsonPath('coverage_verified',false)->assertJsonCount(2,'blockers');
    }
}
