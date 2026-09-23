<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketplace\OAuthStateService;
use App\Services\Marketplace\ShopeeApplicationResolver;
use App\Services\Marketplace\ShopeeAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeOAuthEnvironmentCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sg_production_callback_uses_bound_application_and_verified_region(): void
    {
        config([
            'app.url' => 'https://ecom.example.test',
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123',
            'marketplace.shopee.partner_key' => 'sandbox-key',
            'marketplace.shopee.callback_url' => 'https://ecom.example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '123', 'partner_key' => 'sandbox-key'],
            'marketplace.shopee.applications.production' => ['partner_id' => '456', 'partner_key' => 'production-key'],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'partner.shopeemobile.com/api/v2/auth/token/get*' => Http::response([
                'access_token' => 'sg-production-access', 'refresh_token' => 'sg-production-refresh', 'expire_in' => 14400,
            ]),
            'partner.shopeemobile.com/api/v2/shop/get_shop_info*' => Http::response([
                'shop_id' => 227929252, 'shop_name' => 'Singapore Store', 'region' => 'SG',
            ]),
        ]);
        $actor = User::factory()->create(['is_admin' => true]);
        $context = app(ShopeeApplicationResolver::class)->forEnvironment('production');
        $state = app(OAuthStateService::class)->issue($actor->id, 'shopee', config('app.url'), null, $context);

        $storeId = app(ShopeeAuthorizationService::class)->complete($state, $actor->id, 'code', '227929252');

        $this->assertDatabaseHas('marketplace_stores', ['id' => $storeId, 'environment' => 'production',
            'region' => 'SG', 'shop_name' => 'Singapore Store']);
        $this->assertDatabaseHas('marketplace_store_tokens', ['store_id' => $storeId, 'application_id' => '456']);
        $this->assertNotSame('sg-production-access', DB::table('marketplace_store_tokens')->where('store_id', $storeId)->value('access_token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'partner.shopeemobile.com'));
    }
}
