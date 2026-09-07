<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceReadOnlyGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_release_exposes_catalog_and_orders_but_not_import(): void
    {
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>true,
            'marketplace.import_operations_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/catalog')->assertOk();
        $this->getJson('/admin/api/marketplace/orders')->assertOk();
        $this->postJson('/admin/api/marketplace/listings/1/import-product')->assertStatus(503);
        $this->postJson('/admin/api/marketplace/listings/1/import-variant')->assertStatus(503);
        // Reading does not make remote publishing available.
        $this->postJson('/admin/api/marketplace/remote/publish')->assertStatus(409);
        config(['marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'fixture-only',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('capabilities.shopee.catalog', true)->assertJsonPath('capabilities.shopee.orders', true)
            ->assertJsonPath('capabilities.shopee.publish', false)->assertJsonPath('capabilities.shopee.inventory', false);
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson('/admin/api/marketplace/orders')->assertStatus(503);
    }
}
