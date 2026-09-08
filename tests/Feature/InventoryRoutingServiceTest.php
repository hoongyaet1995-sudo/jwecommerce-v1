<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\InventoryRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_session_and_records_server_actor(): void
    {
        $url='/admin/api/inventory/fulfillment-routes';
        $this->getJson($url)->assertUnauthorized();
        $actor=User::factory()->create(['is_admin'=>true]);
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'API','name'=>'API','status'=>'inactive','is_active'=>false]);
        $payload=['channel_key'=>'website','warehouse_id'=>$warehouse,'version'=>null,'is_enabled'=>false];
        $this->actingAs($actor)->putJson($url,$payload+['actor_id'=>999])->assertUnprocessable();
        $this->putJson($url,$payload)->assertOk()->assertJsonPath('channel_key','website');
        $this->assertDatabaseHas('inventory_route_changes',['actor_id'=>$actor->id]);
        $this->getJson($url)->assertOk()->assertJsonCount(1,'data');
        $this->putJson($url,array_replace($payload,['version'=>0,'is_enabled'=>true]))->assertUnprocessable();
        $this->assertDatabaseCount('inventory_route_changes',1);
    }

    public function test_route_updates_require_current_version(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $warehouse=DB::table('inventory_warehouses')->insertGetId(['code'=>'ROUTE','name'=>'Route','status'=>'active','is_active'=>true]);
        $service=app(InventoryRoutingService::class);
        $created=$service->save('website',$warehouse,null,null,true,$actor);
        $this->assertSame(0,(int)$created['version']);
        $updated=$service->save('website',$warehouse,0,null,false,$actor);
        $this->assertSame(1,(int)$updated['version']);
        try{$service->save('website',$warehouse,0,null,true,$actor);$this->fail('Stale route accepted');}
        catch(ValidationException $error){$this->assertStringContainsString('changed',$error->getMessage());}
        $this->assertDatabaseHas('inventory_fulfillment_routes',['channel_key'=>'website','is_enabled'=>false,'version'=>1]);
    }
}
