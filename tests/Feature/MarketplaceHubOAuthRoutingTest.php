<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceHubOAuthRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopee_start_registers_a_signed_hub_intent_and_preserves_local_state(): void
    {
        $this->configureHub();
        config(['marketplace.shopee.enabled' => true, 'marketplace.shopee.environment' => 'production',
            'marketplace.shopee.partner_id' => '2033707', 'marketplace.shopee.partner_key' => 'fixture-key',
            'marketplace.shopee.callback_url' => 'https://marketplace.jwaistudio.online/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.production' => ['partner_id' => '2033707',
                'partner_key' => 'fixture-key',
                'callback_url' => 'https://marketplace.jwaistudio.online/admin/marketplace/oauth/shopee/callback']]);
        $hubState = str_repeat('b', 64);
        Http::fake(['marketplace.jwaistudio.online/api/oauth/intents' => Http::response(['state' => $hubState], 201)]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $url = $this->postJson('/admin/api/marketplace/shopee/authorize')->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        parse_str(parse_url($query['redirect_uri'], PHP_URL_QUERY), $redirectQuery);
        $this->assertSame($hubState, $redirectQuery['state']);
        $this->assertSame(1, DB::table('marketplace_oauth_states')->count());
        Http::assertSent(function ($request) use ($hubState) {
            $body = $request->body();
            $timestamp = $request->header('X-Hub-Timestamp')[0] ?? '';
            $nonce = $request->header('X-Hub-Nonce')[0] ?? '';
            $signature = $request->header('X-Hub-Signature')[0] ?? '';
            $data = json_decode($body, true);
            return $request->url() === 'https://marketplace.jwaistudio.online/api/oauth/intents'
                && ($data['provider'] ?? null) === 'shopee'
                && ($data['domain'] ?? null) === 'ecom.jwaistudio.online'
                && hash('sha256', $data['localState']) === DB::table('marketplace_oauth_states')->value('state_hash')
                && hash_equals(hash_hmac('sha256', "$timestamp.$nonce.$body", 'fixture-client-secret'), $signature)
                && $hubState !== $data['localState'];
        });
    }

    public function test_hub_failure_fails_closed_without_a_provider_url(): void
    {
        $this->configureHub();
        config(['marketplace.lazada.enabled' => true, 'marketplace.lazada.app_key' => '139209',
            'marketplace.lazada.app_secret' => 'fixture-secret',
            'marketplace.lazada.callback_url' => 'https://marketplace.jwaistudio.online/admin/marketplace/oauth/lazada/callback']);
        Http::fake(['marketplace.jwaistudio.online/api/oauth/intents' => Http::response(['message' => 'Unavailable'], 503)]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson('/admin/api/marketplace/lazada/authorize')->assertConflict()->assertDontSee('fixture-secret');
        $this->assertSame(1, DB::table('marketplace_oauth_states')->count());
    }

    public function test_lazada_start_registers_a_signed_hub_intent_and_uses_the_hub_callback(): void
    {
        $this->configureHub();
        config(['marketplace.lazada.enabled' => true, 'marketplace.lazada.app_key' => '139209',
            'marketplace.lazada.app_secret' => 'fixture-secret',
            'marketplace.lazada.callback_url' => 'https://marketplace.jwaistudio.online/admin/marketplace/oauth/lazada/callback']);
        $hubState = str_repeat('f', 64);
        Http::fake(['marketplace.jwaistudio.online/api/oauth/intents' => Http::response(['state' => $hubState], 201)]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $url = $this->postJson('/admin/api/marketplace/lazada/authorize')->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('139209', $query['client_id']);
        $this->assertSame($hubState, $query['state']);
        $this->assertSame('https://marketplace.jwaistudio.online/admin/marketplace/oauth/lazada/callback',
            $query['redirect_uri']);
        $this->assertSame(1, DB::table('marketplace_oauth_states')->count());
        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $request->url() === 'https://marketplace.jwaistudio.online/api/oauth/intents'
                && ($data['provider'] ?? null) === 'lazada'
                && ($data['environment'] ?? null) === 'production'
                && hash('sha256', $data['localState'] ?? '') === DB::table('marketplace_oauth_states')->value('state_hash');
        });
    }

    public function test_only_shopee_sandbox_can_be_switched_to_hub_while_production_stays_direct(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.oauth_routing_enabled' => false,
            'marketplace.hub.oauth_routes.shopee.sandbox' => true,
            'marketplace.hub.oauth_routes.shopee.production' => false,
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '1232619',
                'partner_key' => 'fixture-sandbox-key',
                'callback_url' => 'https://marketplace.jwaistudio.online/admin/marketplace/oauth/shopee/callback'],
            'marketplace.shopee.applications.production' => ['partner_id' => '2033707',
                'partner_key' => 'fixture-production-key',
                'callback_url' => 'https://ecom.jwaistudio.online/admin/marketplace/oauth/shopee/callback'],
            'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '139209',
            'marketplace.lazada.app_secret' => 'fixture-lazada-secret',
            'marketplace.lazada.callback_url' => 'https://ecom.jwaistudio.online/admin/marketplace/oauth/lazada/callback',
        ]);
        $hubState = str_repeat('a', 64);
        Http::fake(['marketplace.jwaistudio.online/api/oauth/intents' => Http::response(['state' => $hubState], 201)]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $sandbox = $this->postJson('/admin/api/marketplace/shopee/authorize', ['environment' => 'sandbox'])
            ->assertOk()->json('url');
        $production = $this->postJson('/admin/api/marketplace/shopee/authorize', ['environment' => 'production'])
            ->assertOk()->json('url');
        $lazada = $this->postJson('/admin/api/marketplace/lazada/authorize')->assertOk()->json('url');
        parse_str(parse_url($sandbox, PHP_URL_QUERY), $sandboxQuery);
        parse_str(parse_url($production, PHP_URL_QUERY), $productionQuery);
        parse_str(parse_url($lazada, PHP_URL_QUERY), $lazadaQuery);
        $this->assertStringStartsWith('https://marketplace.jwaistudio.online/', $sandboxQuery['redirect_uri']);
        $this->assertStringStartsWith('https://ecom.jwaistudio.online/', $productionQuery['redirect_uri']);
        $this->assertSame('https://ecom.jwaistudio.online/admin/marketplace/oauth/lazada/callback',
            $lazadaQuery['redirect_uri']);
        Http::assertSentCount(1);
    }

    private function configureHub(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.oauth_routing_enabled' => true,
            'marketplace.hub.oauth_routes.shopee.production' => true,
            'marketplace.hub.oauth_routes.lazada.production' => true,
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret']);
    }
}
