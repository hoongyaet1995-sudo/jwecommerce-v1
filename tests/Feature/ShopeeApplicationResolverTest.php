<?php

namespace Tests\Feature;

use App\Services\Marketplace\ShopeeApplicationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopeeApplicationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_store_resolves_its_own_application_and_rejects_a_wrong_token_application(): void
    {
        config([
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.callback_url' => 'https://ecom.example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '123', 'partner_key' => 'sandbox-key'],
            'marketplace.shopee.applications.production' => ['partner_id' => '456', 'partner_key' => 'production-key'],
        ]);

        $sandbox = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'sandbox', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Sandbox', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $production = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '227929252',
            'shop_name' => 'SG Production', 'region' => 'SG', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_tokens')->insert([
            ['store_id' => $sandbox, 'application_id' => '123', 'access_token' => 'a', 'refresh_token' => 'r', 'created_at' => now(), 'updated_at' => now()],
            ['store_id' => $production, 'application_id' => '456', 'access_token' => 'a', 'refresh_token' => 'r', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resolver = app(ShopeeApplicationResolver::class);
        $this->assertSame('https://openplatform.sandbox.test-stable.shopee.sg', $resolver->forStore($sandbox)->apiHost);
        $this->assertSame('https://partner.shopeemobile.com', $resolver->forStore($production)->apiHost);

        DB::table('marketplace_store_tokens')->where('store_id', $production)->update(['application_id' => '123']);
        $this->expectException(\RuntimeException::class);
        $resolver->forStore($production);
    }
}
