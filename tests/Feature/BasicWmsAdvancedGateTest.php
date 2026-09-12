<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicWmsAdvancedGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_release_rejects_advanced_mutations_before_validation():void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.advanced_wms_enabled'=>false,
            'marketplace.warehouse_transfers_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach (['reconciliations','mode-reviews','transfers','transfers/1/transition'] as $path) {
            $this->postJson('/admin/api/inventory/'.$path,[])->assertForbidden();
        }
        $this->assertDatabaseCount('inventory_reconciliations',0);
        $this->assertDatabaseHas('inventory_configuration',['id'=>1,'accounting_enabled'=>false]);
    }
}
