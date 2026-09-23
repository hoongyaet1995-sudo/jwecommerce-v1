<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketplaceHubOrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceHubEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_event_is_queued_once_and_wrong_signature_is_rejected(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.event_endpoint_enabled' => true,
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret']);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Lazada MY', 'external_shop_id' => '300927968304',
            'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Queue::fake();
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'operation' => 'deliver_event',
            'eventId' => '42', 'clientStoreId' => (string) $storeId,
            'type' => 'ORDER_STATUS_CHANGED', 'externalOrderId' => '123456'], JSON_THROW_ON_ERROR);
        $this->deliver($body, 'event-nonce-first', 'incorrect-secret')->assertUnauthorized();
        $this->deliver($body, 'event-nonce-second', 'fixture-client-secret')->assertOk()
            ->assertJsonPath('status', 'accepted');
        $this->deliver($body, 'event-nonce-third', 'fixture-client-secret')->assertOk();
        $this->assertDatabaseCount('marketplace_hub_event_inbox', 1);
        Queue::assertPushed(ProcessMarketplaceHubOrderEvent::class, 1);
    }

    private function deliver(string $body, string $nonce, string $secret)
    {
        $timestamp = (string) now()->timestamp;
        return $this->call('POST', '/api/marketplace/hub/events', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_CLIENT' => 'jw_fixture_client', 'HTTP_X_HUB_TIMESTAMP' => $timestamp,
            'HTTP_X_HUB_NONCE' => $nonce,
            'HTTP_X_HUB_SIGNATURE' => hash_hmac('sha256', "$timestamp.$nonce.$body", $secret),
        ], $body);
    }
}
