<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketplaceHubOrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
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

    public function test_delivered_lazada_order_event_creates_a_local_order_snapshot(): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.event_endpoint_enabled' => true,
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.read_operations_enabled' => true,
            'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '139209',
            'marketplace.lazada.app_secret' => 'fixture-app-secret']);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Lazada MY', 'external_shop_id' => '300927968304',
            'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Models\MarketplaceStoreToken::create(['store_id' => $storeId,
            'application_id' => '139209', 'access_token' => 'fixture-token',
            'refresh_token' => 'fixture-refresh', 'access_expires_at' => now()->addHour(),
            'refresh_expires_at' => now()->addDay()]);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/order/get')) return Http::response(['code' => '0',
                'data' => ['order_id' => '123456', 'updated_at' => '2026-09-23T10:00:00+08:00',
                    'statuses' => ['pending'], 'currency' => 'MYR', 'price' => '25.00']]);
            if (str_contains($request->url(), '/order/items/get')) return Http::response(['code' => '0',
                'data' => [['order_item_id' => '234567', 'order_id' => '123456', 'status' => 'pending']]]);
            return Http::response(['code' => 'denied'], 400);
        });
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'operation' => 'deliver_event',
            'eventId' => '52', 'clientStoreId' => (string) $storeId,
            'type' => 'ORDER_STATUS_CHANGED', 'externalOrderId' => '123456'], JSON_THROW_ON_ERROR);
        $this->deliver($body, 'event-nonce-order-1', 'fixture-client-secret')->assertOk();
        Queue::assertPushedOn('marketplace-hub-orders', ProcessMarketplaceHubOrderEvent::class);
        (new ProcessMarketplaceHubOrderEvent('52'))->handle(app(\App\Services\Marketplace\LazadaOrderService::class));

        $this->assertDatabaseHas('marketplace_orders', ['store_id' => $storeId, 'external_order_id' => '123456']);
        $this->assertDatabaseHas('marketplace_hub_event_inbox', ['hub_event_id' => '52', 'status' => 'completed']);
        Http::assertSentCount(2);
    }

    public function test_hub_event_worker_drains_the_dedicated_queue_and_creates_order(): void
    {
        config(['marketplace.read_operations_enabled' => true,
            'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '139209',
            'marketplace.lazada.app_secret' => 'fixture-app-secret']);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Lazada MY', 'external_shop_id' => '300927968304',
            'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Models\MarketplaceStoreToken::create(['store_id' => $storeId,
            'application_id' => '139209', 'access_token' => 'fixture-token',
            'refresh_token' => 'fixture-refresh', 'access_expires_at' => now()->addHour(),
            'refresh_expires_at' => now()->addDay()]);
        DB::table('marketplace_hub_event_inbox')->insert(['hub_event_id' => '62', 'store_id' => $storeId,
            'event_type' => 'ORDER_STATUS_CHANGED', 'external_order_id' => '123456',
            'status' => 'received', 'created_at' => now(), 'updated_at' => now()]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/order/get')) return Http::response(['code' => '0',
                'data' => ['order_id' => '123456', 'updated_at' => '2026-09-23T10:00:00+08:00',
                    'statuses' => ['pending'], 'currency' => 'MYR', 'price' => '25.00']]);
            if (str_contains($request->url(), '/order/items/get')) return Http::response(['code' => '0',
                'data' => [['order_item_id' => '234567', 'order_id' => '123456', 'status' => 'pending']]]);
            return Http::response(['code' => 'denied'], 400);
        });
        ProcessMarketplaceHubOrderEvent::dispatch('62');
        Artisan::call('marketplace:hub-process-events');

        $this->assertDatabaseHas('marketplace_orders', ['store_id' => $storeId, 'external_order_id' => '123456']);
        $this->assertDatabaseHas('marketplace_hub_event_inbox', ['hub_event_id' => '62', 'status' => 'completed']);
        Http::assertSentCount(2);
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
