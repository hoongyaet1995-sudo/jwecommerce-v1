<?php

namespace Tests\Feature;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Services\Inventory\StoreManagement\ShopeeStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\StockSyncPolicy;
use App\Services\Marketplace\ShopeeClient;
use App\Services\Marketplace\ShopeeTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class ShopeeStoreInventoryPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'marketplace.stock_sync_operations_enabled' => true,
            'marketplace.stock_sync_platforms.shopee' => true,
            'marketplace.stock_sync_canary_enforced' => true,
            'marketplace.shopee.enabled' => true,
            'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '12345',
            'marketplace.shopee.partner_key' => 'secret',
            'marketplace.shopee.callback_url' => 'https://example.test/admin/marketplace/oauth/shopee/callback',
            'marketplace.shopee.applications.sandbox' => [
                'partner_id' => '12345', 'partner_key' => 'secret',
                'callback_url' => 'https://example.test/admin/marketplace/oauth/shopee/callback',
            ],
        ]);
    }

    public function test_model_stock_is_written_to_one_exact_location_and_independently_read_back(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()
            ->push($this->item(true))->push($this->models(4))
            ->push($this->writeSuccess(7))->push($this->item(true))->push($this->models(7))]);

        $receipt = $publisher->publish($store, $listing, 7, 'event-1');

        $this->assertSame('shopee', $receipt['provider']);
        $this->assertSame(7, $receipt['observed_quantity']);
        $this->assertSame('REQ-1', $receipt['request_id']);
        Http::assertSentCount(5);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/update_stock')
            && data_get($request->data(), 'stock_list.0.seller_stock.0.location_id') === 'LOC-1'
            && data_get($request->data(), 'stock_list.0.seller_stock.0.stock') === 7);
    }

    public function test_non_model_item_uses_model_zero_and_single_verified_location(): void
    {
        [$publisher, $store, $listing] = $this->publisher('0');
        Http::fake(['*' => Http::sequence()->push($this->item(false, 3))->push($this->writeSuccess(5, 0))->push($this->item(false, 5))]);

        $this->assertSame(5, $publisher->publish($store, $listing, 5, 'event-2')['observed_quantity']);
        Http::assertSentCount(3);
    }

    public function test_partial_success_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))->push([
            'error' => '', 'response' => ['success_list' => [['model_id' => 88, 'location_id' => 'LOC-1', 'stock' => 7]],
                'failure_list' => [['model_id' => 89]]],
        ])]);
        try {
            $publisher->publish($store, $listing, 7, 'partial');
            $this->fail('Partial provider result was accepted.');
        } catch (StoreInventoryResultUncertain) {
            $this->assertTrue(true);
        }
    }

    public function test_mismatched_read_back_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))->push($this->writeSuccess(7))
            ->push($this->item(true))->push($this->models(6))]);
        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'mismatch');
    }

    public function test_post_write_authorization_failure_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))->push($this->writeSuccess(7))
            ->push(['error' => 'error_auth', 'message' => 'token expired'], 403)]);

        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'auth-after-write');
    }

    public function test_observation_remains_available_with_write_switches_disabled(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        config([
            'marketplace.stock_sync_operations_enabled' => false,
            'marketplace.stock_sync_platforms.shopee' => false,
            'marketplace.stock_sync_canary_identities' => [],
        ]);
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(6))]);

        $observation = $publisher->observe($store, $listing);
        $this->assertSame(6, $observation['observed_quantity']);
        $this->assertSame('LOC-1', $observation['provider_location_id']);
    }

    public function test_expired_authorization_is_explicit(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::response(['error' => 'error_auth', 'message' => 'token expired'], 403)]);
        try {
            $publisher->publish($store, $listing, 7, 'auth');
            $this->fail('Expired authorization was accepted.');
        } catch (StoreInventoryAuthorizationExpired) {
            $this->assertTrue(true);
        }
    }

    public function test_explicit_rejected_write_is_not_marked_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))
            ->push(['error' => 'invalid_parameter', 'response' => ['success_list' => [], 'failure_list' => [['model_id' => 88]]]], 400)]);
        try {
            $publisher->publish($store, $listing, 7, 'reject');
            $this->fail('Rejected write was accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotInstanceOf(StoreInventoryResultUncertain::class, $error);
        }
    }

    public function test_connection_loss_after_possible_send_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))
            ->whenEmpty(fn () => throw new ConnectionException('lost'))]);
        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'lost-after-send');
    }

    public function test_server_error_after_write_request_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push($this->models(4))
            ->push(['message' => 'temporary provider failure'], 503)]);

        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'server-error-after-send');
    }

    public function test_wrong_location_does_not_claim_a_write(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(['*' => Http::sequence()->push($this->item(true))->push([
            'error' => '', 'response' => ['model' => [[
                'model_id' => 88, 'tier_index' => [],
                'stock_info_v2' => ['seller_stock' => [
                    ['location_id' => 'LOC-1', 'stock' => 4], ['location_id' => 'LOC-2', 'stock' => 4],
                ]],
            ]], 'tier_variation' => []],
        ])]);
        try {
            $publisher->publish($store, $listing, 7, 'wrong-location');
            $this->fail('Ambiguous location was accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotInstanceOf(StoreInventoryResultUncertain::class, $error);
        }
    }

    public function test_connection_loss_before_send_does_not_become_an_uncertain_write(): void
    {
        [$publisher, $store, $listing] = $this->publisher('88');
        Http::fake(fn () => throw new ConnectionException('offline'));

        try {
            $publisher->publish($store, $listing, 7, 'offline-before-send');
            $this->fail('Preflight connection loss was accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotInstanceOf(StoreInventoryResultUncertain::class, $error);
        }
    }

    private function publisher(string $modelId): array
    {
        $store = (new StoreInventoryFixture)->store('shopee', strtoupper(bin2hex(random_bytes(3))));
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'external_shop_id' => '999', 'environment' => 'sandbox',
        ]);
        \App\Models\MarketplaceStoreToken::create(['store_id'=>$store->id,'application_id'=>'12345',
            'access_token'=>'token','refresh_token'=>'refresh','access_expires_at'=>now()->addHour()]);
        $store = DB::table('marketplace_stores')->find($store->id);
        $listing = (object) ['id' => 41, 'store_id' => $store->id, 'external_product_id' => '77', 'external_variant_id' => $modelId];
        config(['marketplace.stock_sync_canary_identities' => ["shopee:{$store->id}:41"]]);
        $tokens = Mockery::mock(ShopeeTokenManager::class);
        $tokens->shouldReceive('accessToken')->andReturn('token');

        return [new ShopeeStoreInventoryPublisher($tokens, app(ShopeeClient::class), app(StockSyncPolicy::class)), $store, $listing];
    }

    private function item(bool $hasModel, int $stock = 4): array
    {
        return ['error' => '', 'response' => ['item_list' => [[
            'item_id' => 77, 'has_model' => $hasModel,
            'stock_info_v2' => ['seller_stock' => [['location_id' => 'LOC-1', 'stock' => $stock]]],
        ]]]];
    }

    private function models(int $stock): array
    {
        return ['error' => '', 'response' => ['model' => [[
            'model_id' => 88, 'tier_index' => [],
            'stock_info_v2' => ['seller_stock' => [['location_id' => 'LOC-1', 'stock' => $stock]]],
        ]], 'tier_variation' => []]];
    }

    private function writeSuccess(int $stock, int $modelId = 88): array
    {
        return ['error' => '', 'request_id' => 'REQ-1', 'response' => [
            'success_list' => [['model_id' => $modelId, 'location_id' => 'LOC-1', 'stock' => $stock]], 'failure_list' => [],
        ]];
    }
}
