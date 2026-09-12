<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicWmsShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_ui_release_is_separate_from_basic_operations_and_query_cannot_enable_it_online(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.basic_wms_ui_enabled'=>false]);
        $this->app->instance('env','production');
        $this->get('/admin/marketplace/inventory?basic_wms_preview=1')->assertOk()
            ->assertViewHas('bootstrap',fn($data)=>$data['basicWmsUiEnabled']===false);
        config(['marketplace.basic_wms_ui_enabled'=>true]);
        $this->get('/admin/marketplace/inventory')->assertOk()
            ->assertViewHas('bootstrap',fn($data)=>$data['basicWmsUiEnabled']===true);
    }

    public function test_local_explicit_shell_preview_does_not_require_official_cutover(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        config(['marketplace.basic_wms_ui_enabled'=>false]);
        $this->app->instance('env','local');
        $this->get('/admin/marketplace/inventory?basic_wms_preview=1')->assertOk()
            ->assertViewHas('bootstrap',fn($data)=>$data['basicWmsUiEnabled']===true && $data['basicWmsUiPreview']===true);
        $this->get('/admin/marketplace/inventory')->assertOk()
            ->assertViewHas('bootstrap',fn($data)=>$data['basicWmsUiEnabled']===false);
    }
}
