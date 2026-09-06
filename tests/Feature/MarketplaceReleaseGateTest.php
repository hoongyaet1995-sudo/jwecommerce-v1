<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_only_release_rejects_direct_operational_requests_before_side_effects(): void
    {
        Http::preventStrayRequests();
        config(['marketplace.operations_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach (['stores/1/catalog-fetch', 'stores/1/orders-fetch', 'catalog-runs/1/retry',
            'order-runs/1/retry', 'listings/1/import-product'] as $path) {
            $this->postJson('/admin/api/marketplace/'.$path)->assertStatus(503)
                ->assertJsonPath('code', 'marketplace_operations_disabled');
        }
        $this->getJson('/admin/api/marketplace/connections')->assertOk();
        $this->assertDatabaseCount('marketplace_catalog_runs', 0);
        $this->assertDatabaseCount('marketplace_order_runs', 0);
        $this->assertDatabaseCount('marketplace_product_imports', 0);
        Http::assertNothingSent();
    }
}
