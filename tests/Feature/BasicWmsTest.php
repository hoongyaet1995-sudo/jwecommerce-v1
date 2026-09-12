<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\BasicWmsConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BasicWmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_basic_wms_blocks_setup_mutations_but_allows_reads():void
    {
        config(['marketplace.basic_wms_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/inventory/warehouses',[])->assertForbidden();
        $this->putJson('/admin/api/inventory/warehouses/1',[])->assertForbidden();
        $this->putJson('/admin/api/inventory/fulfillment-routes',[])->assertForbidden();
        $this->postJson('/admin/api/inventory/balances',[])->assertForbidden();
        $this->getJson('/admin/api/inventory/warehouses')->assertOk();
        $this->getJson('/admin/api/inventory/fulfillment-routes')->assertOk();
        $this->assertDatabaseCount('inventory_warehouses',0);
        $this->assertDatabaseCount('inventory_balances',0);
        $this->assertDatabaseCount('inventory_fulfillment_routes',0);
    }

    public function test_disabled_basic_wms_blocks_manual_stock_endpoints():void
    {
        config(['marketplace.basic_wms_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(['/admin/api/inventory/count-reviews','/admin/api/inventory/adjustment-reviews',
            '/admin/api/inventory/count-reviews/'.Str::uuid().'/confirm'] as $url){
            $this->postJson($url,[])->assertForbidden();
        }
        $this->assertDatabaseCount('inventory_count_reviews',0);
        $this->assertDatabaseCount('inventory_physical_movements',0);
    }

    public function test_basic_release_rejects_transfer_writes_before_payload_processing():void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.warehouse_transfers_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/inventory/transfers',[])->assertForbidden();
        $this->postJson('/admin/api/inventory/transfers/1/transition',[])->assertForbidden();
        $this->assertDatabaseCount('inventory_transfers',0);
    }

    public function test_initial_mode_does_not_require_counted_catalog_or_take_over_legacy_stock(): void
    {
        config(['marketplace.basic_wms_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Basic','handle'=>'basic','base_price'=>10])->variants()
            ->create(['sku'=>'BASIC-TEST','title'=>'One','price'=>10,'stock'=>17,'enabled'=>true]);
        $key=(string)Str::uuid();
        $service=app(BasicWmsConfigurationService::class);
        $receipt=$service->configure('shared_pool',0,'Initial Basic WMS setup',$key,$actor);
        $this->assertSame($receipt,$service->configure('shared_pool',0,'Initial Basic WMS setup',$key,$actor));
        $this->assertSame(1,$receipt['configuration_version']);
        $this->assertDatabaseHas('inventory_configuration',['id'=>1,'operating_mode'=>'shared_pool','accounting_enabled'=>false]);
        $this->assertSame(17,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_balances',0);
        $this->assertDatabaseCount('basic_wms_configuration_events',1);
        $this->actingAs($actor)->getJson('/admin/api/inventory/readiness')->assertOk()
            ->assertJsonPath('basic_setup_audit.id',$key)
            ->assertJsonPath('basic_setup_audit.staffName',$actor->name)
            ->assertJsonPath('basic_setup_audit.toMode','shared_pool');
    }

    public function test_configuration_endpoint_is_disabled_by_default():void
    {
        config(['marketplace.basic_wms_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson('/admin/api/inventory/basic/configuration',[
            'mode'=>'shared_pool','version'=>0,'reason'=>'Setup','operation_key'=>(string)Str::uuid(),
        ])->assertForbidden();
        $this->assertNull(DB::table('inventory_configuration')->value('operating_mode'));
    }

    public function test_configured_mode_cannot_be_changed_in_basic_wms():void
    {
        config(['marketplace.basic_wms_enabled'=>true]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson('/admin/api/inventory/basic/configuration',[
            'mode'=>'independent_warehouses','version'=>0,'reason'=>'Switch','operation_key'=>(string)Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseHas('inventory_configuration',['id'=>1,'operating_mode'=>'shared_pool','version'=>0]);
    }

    public function test_provisioning_cannot_create_a_conflicting_pool_after_setup():void
    {
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'independent_warehouses']);
        $variant=Product::create(['title'=>'Provision','handle'=>'provision','base_price'=>10])->variants()
            ->create(['sku'=>'PROVISION-TEST','title'=>'One','price'=>10,'stock'=>17,'enabled'=>true]);
        try {
            app(\App\Services\Inventory\InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
            $this->fail('Conflicting pool accepted.');
        } catch(\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('pool_kind',$error->errors());
        }
        $this->assertDatabaseCount('inventory_balances',0);
    }
}
