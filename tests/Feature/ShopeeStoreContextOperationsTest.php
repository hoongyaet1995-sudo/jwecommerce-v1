<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\ShopeeTokenManager;
use App\Services\Marketplace\ShopeeCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeStoreContextOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_store_refresh_uses_its_own_application_while_global_is_sandbox(): void
    {
        config([
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123',
            'marketplace.shopee.partner_key' => 'sandbox-key',
            'marketplace.shopee.callback_url' => 'https://ecom.example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.production' => ['partner_id' => '456', 'partner_key' => 'production-key'],
        ]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Store', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MarketplaceStoreToken::create(['store_id' => $storeId, 'application_id' => '456',
            'access_token' => 'old-access', 'refresh_token' => 'old-refresh', 'access_expires_at' => now()->subMinute()]);
        Http::preventStrayRequests();
        Http::fake(['partner.shopeemobile.com/api/v2/auth/access_token/get*' => Http::response([
            'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expire_in' => 14400,
        ])]);

        $this->assertSame('new-access', app(ShopeeTokenManager::class)->accessToken($storeId));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'partner.shopeemobile.com')
            && str_contains($request->url(), 'partner_id=456'));
    }

    public function test_production_catalog_uses_production_host_with_a_valid_store_token(): void
    {
        config([
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123',
            'marketplace.shopee.partner_key' => 'sandbox-key',
            'marketplace.shopee.callback_url' => 'https://ecom.example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.production' => ['partner_id' => '456', 'partner_key' => 'production-key'],
        ]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Store', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MarketplaceStoreToken::create(['store_id' => $storeId, 'application_id' => '456',
            'access_token' => 'valid-access', 'refresh_token' => 'valid-refresh', 'access_expires_at' => now()->addHour()]);
        Http::preventStrayRequests();
        Http::fake(['partner.shopeemobile.com/api/v2/product/get_item_list*' => Http::response([
            'error' => '', 'response' => ['total_count' => 0, 'has_next_page' => false],
        ])]);

        $this->assertSame(0, app(ShopeeCatalogService::class)->pullPage($storeId, 0)['items']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'partner.shopeemobile.com')
            && str_contains($request->url(), 'partner_id=456'));
    }
}
