<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Category;
use App\Models\TaxClass;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_persist_tax_configuration_zones_classes_and_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/admin/api/tax-settings/configuration', [
            'isEnabled' => true,
            'isPublished' => true,
            'pricesIncludeTax' => false,
            'addressBasis' => 'shipping_address',
            'merchantComplianceMetadata' => ['registrationNumber' => 'optional'],
        ])->assertOk()->assertJsonPath('data.configuration.isEnabled', true);

        $zone = $this->actingAs($admin)->postJson('/admin/api/tax-settings/zones', [
            'name' => 'Malaysia domestic', 'description' => 'Domestic tax jurisdiction',
            'countryIds' => ['MY'], 'zoneIds' => ['Selangor'], 'isActive' => true,
        ])->assertCreated()->json('data');

        $class = $this->actingAs($admin)->postJson('/admin/api/tax-settings/classes', [
            'name' => 'Standard goods', 'description' => 'Merchant configured', 'defaultRate' => 8,
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson('/admin/api/tax-settings/rules', [
            'taxClassId' => $class['id'], 'geoZoneId' => $zone['id'], 'description' => 'Merchant SST',
            'ratePercent' => 8, 'priority' => 1, 'compound' => false, 'isEnabled' => true,
        ])->assertCreated()->assertJsonPath('data.ratePercent', 8);

        $this->assertDatabaseHas('tax_rules', ['label' => 'Merchant SST', 'rate_percent' => '8.0000']);
        $this->actingAs($admin)->getJson('/admin/api/tax-settings')
            ->assertOk()->assertJsonCount(1, 'data.zones')->assertJsonCount(1, 'data.classes')->assertJsonCount(1, 'data.rules');
    }

    public function test_empty_tax_document_has_no_chargeable_mock_rule_or_rate(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->getJson('/admin/api/tax-settings')
            ->assertOk()
            ->assertJsonPath('data.configuration.isEnabled', false)
            ->assertJsonPath('data.configuration.isPublished', false)
            ->assertJsonPath('data.classes', [])
            ->assertJsonPath('data.rules', []);
    }

    public function test_tax_writes_require_admin_authentication(): void
    {
        $this->putJson('/admin/api/tax-settings/configuration', [])->assertUnauthorized();
        $this->postJson('/admin/api/tax-settings/zones', [])->assertUnauthorized();
        $this->postJson('/admin/api/tax-settings/classes', [])->assertUnauthorized();
        $this->postJson('/admin/api/tax-settings/rules', [])->assertUnauthorized();
    }

    public function test_tax_api_validates_references_rate_country_and_effective_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/admin/api/tax-settings/zones', [
            'name' => 'Invalid', 'countryIds' => ['MYS'], 'zoneIds' => [], 'isActive' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['countryIds.0']);

        $this->actingAs($admin)->postJson('/admin/api/tax-settings/rules', [
            'taxClassId' => 999, 'geoZoneId' => 998, 'description' => 'Invalid', 'ratePercent' => 101,
            'priority' => 0, 'compound' => false, 'isEnabled' => true,
            'effectiveFrom' => '2026-02-01', 'effectiveUntil' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['taxClassId', 'geoZoneId', 'ratePercent', 'effectiveUntil']);
    }

    public function test_referenced_classes_and_zones_cannot_be_destroyed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $class = TaxClass::factory()->create();
        $zone = TaxZone::factory()->create();
        TaxRule::factory()->create(['tax_class_id' => $class->id, 'tax_zone_id' => $zone->id]);

        $this->actingAs($admin)->deleteJson("/admin/api/tax-settings/classes/{$class->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['taxClass']);
        $this->actingAs($admin)->deleteJson("/admin/api/tax-settings/zones/{$zone->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['taxZone']);
    }

    public function test_tax_class_assigned_to_a_product_cannot_be_destroyed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $class = TaxClass::factory()->create();
        $category = Category::query()->create(['name' => 'Tax test', 'slug' => 'tax-test', 'status' => 'active']);
        Product::query()->create([
            'category_id' => $category->id,
            'tax_class_id' => $class->id,
            'title' => 'Taxable item', 'handle' => 'taxable-item', 'brand' => 'JW Studio',
            'description' => 'Tax test', 'base_price' => 1000, 'status' => 'active',
        ]);

        $this->actingAs($admin)->deleteJson("/admin/api/tax-settings/classes/{$class->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['taxClass']);
    }
}
