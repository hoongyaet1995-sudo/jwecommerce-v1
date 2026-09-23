<?php

namespace Tests\Feature;

use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceHubRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_request_is_rejected_and_valid_not_due_request_never_refreshes(): void
    {
        $this->configureHub();
        $storeId = $this->store(now()->addHour());
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'operation' => 'refresh_store',
            'clientStoreId' => (string) $storeId], JSON_THROW_ON_ERROR);
        $this->post('/api/marketplace/hub/refresh', [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->app->instance(ShopeeTokenManager::class, \Mockery::mock(ShopeeTokenManager::class)
            ->shouldNotReceive('accessToken')->getMock());
        $this->signed($body, 'refresh-nonce-first')->assertOk()->assertJsonPath('status', 'not_due');
        $this->signed($body, 'refresh-nonce-first')->assertStatus(409);
    }

    public function test_near_expiry_refreshes_only_the_addressed_store_without_returning_a_token(): void
    {
        $this->configureHub();
        $storeId = $this->store(now()->addMinute());
        $manager = \Mockery::mock(ShopeeTokenManager::class);
        $manager->shouldReceive('accessToken')->once()->with($storeId)->andReturnUsing(function () use ($storeId) {
            DB::table('marketplace_store_tokens')->where('store_id', $storeId)
                ->update(['access_expires_at' => now()->addHour()]);
            return 'sensitive-access-token';
        });
        $this->app->instance(ShopeeTokenManager::class, $manager);
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'operation' => 'refresh_store',
            'clientStoreId' => (string) $storeId], JSON_THROW_ON_ERROR);
        $this->signed($body, 'refresh-nonce-second')->assertOk()->assertJsonPath('status', 'refreshed')
            ->assertDontSee('sensitive-access-token');
    }

    private function configureHub(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.refresh_endpoint_enabled' => true,
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret']);
    }

    private function store($expiry): int
    {
        $id = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'shop_name' => 'Test Shop', 'external_shop_id' => '12345',
            'region' => 'MY', 'environment' => 'production', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_store_tokens')->insert([
            'store_id' => $id, 'application_id' => '230736', 'access_token' => 'encrypted',
            'refresh_token' => 'encrypted', 'access_expires_at' => $expiry,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function signed(string $body, string $nonce)
    {
        $timestamp = (string) now()->timestamp;
        return $this->call('POST', '/api/marketplace/hub/refresh', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_CLIENT' => 'jw_fixture_client', 'HTTP_X_HUB_TIMESTAMP' => $timestamp,
            'HTTP_X_HUB_NONCE' => $nonce,
            'HTTP_X_HUB_SIGNATURE' => hash_hmac('sha256', "$timestamp.$nonce.$body", 'fixture-client-secret'),
        ], $body);
    }
}
