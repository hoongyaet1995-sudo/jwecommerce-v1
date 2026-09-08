<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\InventoryWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_save_preserves_single_default_and_rejects_stale_changes(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $service=app(InventoryWarehouseService::class);
        $first=$service->save(null,null,['code'=>'CYB','name'=>'Cyberjaya','status'=>'active','is_default_master'=>true],$actor);
        $second=$service->save(null,null,['code'=>'PEN','name'=>'Penang','status'=>'inactive','is_default_master'=>false],$actor);
        $this->assertSame($first['id'],(int)DB::table('inventory_configuration')->value('default_warehouse_id'));
        $updated=$service->save($second['id'],0,['code'=>'PEN','name'=>'Penang','status'=>'active','is_default_master'=>true],$actor);
        $this->assertSame(1,$updated['version']);
        $this->assertSame($second['id'],(int)DB::table('inventory_configuration')->value('default_warehouse_id'));
        $this->assertSame(1,(int)DB::table('inventory_warehouses')->where('id',$first['id'])->value('version'));
        try {$service->save($second['id'],0,['code'=>'PEN','name'=>'Old form','status'=>'active','is_default_master'=>true],$actor);$this->fail('Stale edit accepted');}
        catch(ValidationException $error){$this->assertStringContainsString('changed',$error->getMessage());}
        $this->assertDatabaseHas('inventory_warehouses',['id'=>$second['id'],'name'=>'Penang']);
        $this->assertDatabaseCount('inventory_warehouse_changes',3);
        $this->assertNull(DB::table('inventory_configuration')->value('operating_mode'));
        $this->assertDatabaseCount('inventory_balances',0);
    }

    public function test_nonadmin_and_duplicate_codes_are_rejected(): void
    {
        $service=app(InventoryWarehouseService::class);
        $data=['code'=>'CYB','name'=>'Cyberjaya','status'=>'inactive','is_default_master'=>false];
        try {$service->save(null,null,$data,User::factory()->create(['is_admin'=>false]));$this->fail('Unauthorized save');}
        catch(ValidationException $error){$this->assertStringContainsString('administrator',$error->getMessage());}
        $actor=User::factory()->create(['is_admin'=>true]);
        $service->save(null,null,$data,$actor);
        $this->expectException(ValidationException::class);
        $service->save(null,null,$data,$actor);
    }
}
