<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReadinessTest extends TestCase
{
    use RefreshDatabase;
    public function test_empty_configuration_is_not_presented_as_ready():void
    {
        $this->getJson('/admin/api/inventory/readiness')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson('/admin/api/inventory/readiness')
            ->assertOk()->assertJsonPath('operating_mode',null)->assertJsonPath('activation_ready',false)
            ->assertJsonPath('coverage_verified',false)->assertJsonCount(2,'blockers');
    }
}
