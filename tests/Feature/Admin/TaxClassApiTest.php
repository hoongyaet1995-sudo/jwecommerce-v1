<?php

namespace Tests\Feature\Admin;

use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxClassApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_switch_the_single_active_tax_class(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sst = $this->actingAs($admin)->postJson('/admin/api/tax-classes', [
            'name' => 'SST', 'ratePercent' => '8.0000', 'isActive' => true,
        ])->assertCreated()->assertJsonPath('data.activeTaxClassId', '1')->json('data.classes.0');

        $this->actingAs($admin)->postJson('/admin/api/tax-classes', [
            'name' => 'GST', 'ratePercent' => '6', 'isActive' => false,
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/api/tax-classes/2/activate')
            ->assertOk()->assertJsonPath('data.activeTaxClassId', '2');

        $this->assertDatabaseHas('tax_classes', ['id' => $sst['id'], 'is_active' => false, 'active_slot' => null]);
        $this->assertDatabaseHas('tax_classes', ['id' => 2, 'is_active' => true, 'active_slot' => 1]);
    }

    public function test_tax_class_api_validates_duplicates_rates_and_active_deletion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $active = TaxClass::factory()->create(['name' => 'SST', 'rate_percent' => '8.0000', 'is_active' => true, 'active_slot' => 1]);

        $this->actingAs($admin)->postJson('/admin/api/tax-classes', [
            'name' => 'sst', 'ratePercent' => '101', 'isActive' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'ratePercent']);

        $this->actingAs($admin)->deleteJson("/admin/api/tax-classes/{$active->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['tax_class']);
    }

    public function test_tax_class_writes_require_admin_authentication(): void
    {
        $this->getJson('/admin/api/tax-classes')->assertUnauthorized();
        $this->postJson('/admin/api/tax-classes', [])->assertUnauthorized();
    }
}
