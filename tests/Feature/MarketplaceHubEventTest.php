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

    public function test_admin_can_read_sanitized_hub_event_ledger(): void
    {
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'lazada', 'shop_name' => 'Test store', 'external_shop_id' => '300163725140',
            'region' => 'MY', 'environment' => 'live', 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_hub_event_inbox')->insert([
            'hub_event_id' => '72', 'store_id' => $storeId, 'event_type' => 'ORDER_STATUS_CHANGED',
            'external_order_id' => '510332413643475', 'status' => 'received',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/admin/api/marketplace/hub-events')->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $response = $this->getJson('/admin/api/marketplace/hub-events')->assertOk()
            ->assertJsonPath('events.0.externalOrderId', '510332413643475')
            ->assertJsonPath('events.0.status', 'processing')
            ->assertJsonPath('events.0.platform', 'lazada');
        $this->assertStringNotContainsString('encrypted_payload', $response->getContent());
        $this->assertStringNotContainsString('payloadJson', $response->getContent());

        $orderId = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $storeId, 'external_order_id' => '510332413643475',
            'platform_status' => 'pending', 'platform_updated_at' => 1,
            'encrypted_payload' => 'private-payload', 'fetched_at' => now(),
        ]);
        DB::table('marketplace_inventory_items')->insert([
            'order_id' => $orderId, 'line_key' => '101', 'external_product_id' => '200',
            'external_variant_id' => '300', 'warehouse_code' => 'MY-WH-1',
            'status' => 'skipped_unmapped', 'platform_updated_at' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_hub_event_inbox')->where('hub_event_id', '72')->update(['status' => 'completed']);
        $this->getJson('/admin/api/marketplace/hub-events')->assertOk()
            ->assertJsonPath('events.0.status', 'warning')
            ->assertJsonPath('events.0.attentionCode', 'inventory_line_skipped')
            ->assertDontSee('private-payload');
    }

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
        app()->call([new ProcessMarketplaceHubOrderEvent('52'), 'handle']);

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

    public static function shopeeEnvironments(): array
    {
        return [['sandbox', '123', 'https://openplatform.sandbox.test-stable.shopee.sg/'],
            ['production', '789', 'https://partner.shopeemobile.com/']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shopeeEnvironments')]
    public function test_signed_shopee_event_pulls_exact_order_with_store_environment(string $environment, string $partnerId, string $host): void
    {
        config(['app.url' => 'https://ecom.jwaistudio.online',
            'marketplace.hub.event_endpoint_enabled' => true,
            'marketplace.hub.client_id' => 'jw_fixture_client',
            'marketplace.hub.client_secret' => 'fixture-client-secret',
            'marketplace.read_operations_enabled' => true,
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123',
            'marketplace.shopee.partner_key' => 'sandbox-secret',
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '123',
                'partner_key' => 'sandbox-secret',
                'callback_url' => 'https://example.test/admin/marketplace/oauth/shopee/callback'],
            'marketplace.shopee.applications.production' => ['partner_id' => '789',
                'partner_key' => 'production-secret',
                'callback_url' => 'https://example.test/admin/marketplace/oauth/shopee/callback']]);
        $storeId = DB::table('marketplace_stores')->insertGetId([
            'platform' => 'shopee', 'shop_name' => 'Shopee production', 'external_shop_id' => '456',
            'region' => 'MY', 'environment' => $environment, 'status' => 'connected',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Models\MarketplaceStoreToken::create(['store_id' => $storeId,
            'application_id' => $partnerId, 'access_token' => 'fixture-token',
            'refresh_token' => 'fixture-refresh', 'access_expires_at' => now()->addHour()]);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($partnerId, $host) {
            $this->assertStringStartsWith($host, $request->url());
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame($partnerId, (string) ($query['partner_id'] ?? ''));
            return Http::response(['error' => '', 'response' => ['order_list' => [[
                'order_sn' => 'SHOPEE123', 'order_status' => 'UNPAID',
                'update_time' => 1780000000, 'currency' => 'MYR', 'item_list' => [],
            ]]]]);
        });
        $body = json_encode(['domain' => 'ecom.jwaistudio.online', 'operation' => 'deliver_event',
            'eventId' => '82', 'clientStoreId' => (string) $storeId,
            'type' => 'ORDER_STATUS_CHANGED', 'externalOrderId' => 'SHOPEE123'], JSON_THROW_ON_ERROR);
        $this->deliver($body, 'event-nonce-shopee-1', 'fixture-client-secret')->assertOk();
        Queue::assertPushedOn('marketplace-hub-orders', ProcessMarketplaceHubOrderEvent::class);
        app()->call([new ProcessMarketplaceHubOrderEvent('82'), 'handle']);

        $this->assertDatabaseHas('marketplace_orders', ['store_id' => $storeId, 'external_order_id' => 'SHOPEE123']);
        $this->assertDatabaseHas('marketplace_hub_event_inbox', ['hub_event_id' => '82', 'status' => 'completed']);
        Http::assertSentCount(1);
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
