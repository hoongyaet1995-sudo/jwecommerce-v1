<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_zone_rule_tax_endpoints_are_retired(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/api/tax-settings/zones', [
                'name' => 'Legacy Malaysia Zone',
                'countryIds' => ['MY'],
                'isActive' => true,
            ])
            ->assertGone();

        $this->assertDatabaseCount('tax_zones', 0);
        $this->actingAs($admin)->getJson('/admin/api/tax-settings')->assertGone();
    }
}
