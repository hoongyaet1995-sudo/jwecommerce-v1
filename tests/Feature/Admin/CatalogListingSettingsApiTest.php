<?php

namespace Tests\Feature\Admin;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogListingSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_the_global_shop_catalog_copy_and_it_renders_on_the_root_listing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'eyebrow' => 'Curated product catalog',
            'title' => 'Shop the Studio',
            'description' => 'Current equipment and considered essentials, selected by JW Studio.',
        ];

        $this->actingAs($admin)
            ->putJson('/admin/api/catalog-listing-settings', $payload)
            ->assertOk()
            ->assertExactJson($payload);

        $this->assertDatabaseHas('store_settings', [
            'key' => 'catalog_listing',
            'value' => json_encode(['data' => $payload]),
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Curated product catalog')
            ->assertSee('Shop the Studio')
            ->assertSee('Current equipment and considered essentials, selected by JW Studio.');
    }

    public function test_catalog_listing_settings_routes_require_an_admin(): void
    {
        $this->getJson('/admin/api/catalog-listing-settings')->assertUnauthorized();
        $this->putJson('/admin/api/catalog-listing-settings', [])->assertUnauthorized();
    }

    public function test_admin_bootstrap_exposes_the_catalog_listing_settings_endpoint(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertOk();

        preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $response->getContent(), $matches);
        $bootstrap = json_decode($matches[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            route('admin.api.catalog-listing-settings.show'),
            $bootstrap['catalogListingSettingsUrl'] ?? null,
        );
    }

    public function test_collection_badge_and_subtitle_round_trip_to_its_public_listing_header(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $collection = Collection::query()->create([
            'title' => 'Studio Editions',
            'slug' => 'studio-editions',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api/catalog/collections/'.$collection->id, [
                'title' => 'Studio Editions',
                'slug' => 'studio-editions',
                'description' => 'Campaign copy remains distinct from the listing introduction.',
                'badge' => 'Limited release',
                'subtitle' => 'A small selection of precision-built studio equipment.',
                'coverImage' => null,
                'type' => 'manual',
                'manualProductIds' => [],
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.badge', 'Limited release')
            ->assertJsonPath('data.subtitle', 'A small selection of precision-built studio equipment.');

        $this->get('/collections/studio-editions')
            ->assertOk()
            ->assertSee('Limited release')
            ->assertSee('A small selection of precision-built studio equipment.');
    }
}
