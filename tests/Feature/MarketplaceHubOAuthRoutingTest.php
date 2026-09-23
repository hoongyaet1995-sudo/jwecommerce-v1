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

    private function configureHub(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.oauth_routing_enabled' => true,
            'marketplace.hub.url' => 'https://marketplace.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret']);
    }
}
