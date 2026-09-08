<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWarehouseApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_can_create_read_and_edit_but_cannot_override_actor_or_use_stale_version(): void
    {
        $url='/admin/api/inventory/warehouses';
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url,[])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $data=['code'=>'cyb','name'=>'Cyberjaya','status'=>'active','is_default_master'=>true];
        $this->postJson($url,$data+['actor_id'=>999])->assertUnprocessable();
        $id=$this->postJson($url,$data)->assertCreated()->assertJsonPath('code','CYB')->json('id');
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id',$id)
            ->assertJsonPath('data.0.is_default_master',true)->assertJsonPath('data.0.supported_carriers',[]);
        $this->putJson($url.'/'.$id,$data)->assertUnprocessable();
        $this->putJson($url.'/'.$id,$data+['version'=>0])->assertOk()->assertJsonPath('version',1);
        $this->putJson($url.'/'.$id,$data+['version'=>0])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_warehouse_changes',2);
    }
}
