<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\ShopeeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceHubShopeeVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_matching_application_can_verify_one_existing_store_without_returning_tokens(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.hub.verification_endpoint_enabled' => true,
            'marketplace.shopee.applications.production.partner_id' => '2033707',
            'marketplace.shopee.applications.production.partner_key' => 'fixture-partner-secret']);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'environment' => 'production', 'external_shop_id' => '12345',
            'shop_name' => 'Fixture Shop', 'region' => 'MY', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MarketplaceStoreToken::create(['store_id' => $storeId, 'application_id' => '2033707',
            'access_token' => 'sensitive-token', 'refresh_token' => 'sensitive-refresh',
            'access_expires_at' => now()->addHour()]);
        $this->mock(ShopeeClient::class)->shouldReceive('shopInfoWithApplication')->once()
            ->with('sensitive-token', '12345', 'production', '2033707', 'fixture-partner-secret')
            ->andReturn(['shop_id' => '12345', 'shop_name' => 'Fixture Shop', 'region' => 'MY']);
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'environment' => 'production',
            'applicationId' => '2033707', 'clientStoreId' => (string) $storeId,
            'challenge' => str_repeat('a', 32),
            'applicationProof' => hash_hmac('sha256', 'shopee|production|2033707|'.str_repeat('a', 32), 'fixture-partner-secret')], JSON_THROW_ON_ERROR);
        $this->post('/api/marketplace/hub/verify-shopee', [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->signed($body)->assertOk()->assertJsonPath('status', 'verified')
            ->assertJsonPath('shopId', '12345')->assertDontSee('sensitive-token');
        $mismatch = json_encode(['domain' => 'ecom.jwaistudio.online', 'environment' => 'production',
            'applicationId' => '2033707', 'clientStoreId' => (string) $storeId,
            'challenge' => str_repeat('b', 32), 'applicationProof' => str_repeat('0', 64)], JSON_THROW_ON_ERROR);
        $this->signed($mismatch, 'verify-test-nonce-456')->assertStatus(409)
            ->assertJsonPath('status', 'application_mismatch');
    }

    private function signed(string $body, string $nonce = 'verify-test-nonce-123')
    {
        $timestamp = (string) now()->timestamp;
        return $this->call('POST', '/api/marketplace/hub/verify-shopee', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_HUB_CLIENT' => 'jw_fixture_client',
            'HTTP_X_HUB_TIMESTAMP' => $timestamp, 'HTTP_X_HUB_NONCE' => $nonce,
            'HTTP_X_HUB_SIGNATURE' => hash_hmac('sha256', "$timestamp.$nonce.$body", 'fixture-client-secret'),
        ], $body);
    }
}
