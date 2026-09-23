<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopeeOAuthEnvironmentStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_binds_the_selected_environment_and_application_to_state(): void
    {
        config([
            'app.url' => 'https://ecom.example.test',
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.callback_url' => 'https://ecom.example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '123', 'partner_key' => 'sandbox-key'],
            'marketplace.shopee.applications.production' => ['partner_id' => '456', 'partner_key' => 'production-key'],
        ]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $sandbox = $this->postJson('/admin/api/marketplace/shopee/authorize', ['environment' => 'sandbox'])->assertOk();
        $production = $this->postJson('/admin/api/marketplace/shopee/authorize', ['environment' => 'production'])->assertOk();

        $this->assertSame('open.sandbox.test-stable.shopee.com', parse_url($sandbox->json('url'), PHP_URL_HOST));
        $this->assertSame('open.shopee.com', parse_url($production->json('url'), PHP_URL_HOST));
        $this->assertDatabaseHas('marketplace_oauth_states', ['environment' => 'sandbox', 'application_id' => '123']);
        $this->assertDatabaseHas('marketplace_oauth_states', ['environment' => 'production', 'application_id' => '456']);
        $this->assertSame(2, DB::table('marketplace_oauth_states')->count());
    }
}
