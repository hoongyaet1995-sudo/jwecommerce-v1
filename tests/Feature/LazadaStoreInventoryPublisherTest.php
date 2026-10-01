<?php

namespace Tests\Feature;

use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\StoreManagement\LazadaStoreInventoryPublisher;
use App\Services\Marketplace\LazadaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class LazadaStoreInventoryPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Http::preventStrayRequests();
        \Illuminate\Support\Facades\Queue::fake();
        config([
            'marketplace.stock_sync_operations_enabled' => true,
            'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => true,
            'marketplace.lazada.enabled' => true,
            'marketplace.lazada.app_key' => '123',
            'marketplace.lazada.app_secret' => 'secret',
            'marketplace.lazada.test_app_key' => '123',
            'marketplace.lazada.test_app_secret' => 'secret',
        ]);
    }

    public function test_one_item_call_returns_multiple_skus_and_warehouses(): void
    {
        Http::fake(['*' => Http::response(['code' => '0', 'data' => [
            'item_id' => '1001', 'skus' => [
                ['sku_id' => '2001', 'multiWarehouseInventories' => [
                    ['warehouseCode' => 'WH-A', 'sellableQuantity' => '10'],
                    ['warehouseCode' => 'WH-B', 'sellableQuantity' => '20'],
                ]],
                ['sku_id' => '2002', 'multiWarehouseInventories' => [
                    ['warehouseCode' => 'WH-A', 'sellableQuantity' => '30'],
                ]],
            ],
        ]])]);
        $stock = app(LazadaClient::class)->itemStockSnapshot('token', '1001');
        $this->assertSame(['2001' => ['WH-A' => 10, 'WH-B' => 20], '2002' => ['WH-A' => 30]], $stock);
        Http::assertSentCount(1);
    }

    public function test_single_stock_identity_is_written_and_read_back_exactly(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::sequence()->push($this->item(3))->push(['code' => '0', 'request_id' => 'REQ-1'])->push($this->item(7))]);

        $receipt = $publisher->publish($store, $listing, 7, 'event-lazada');

        $this->assertSame(7, $receipt['observed_quantity']);
        $this->assertSame('REQ-1', $receipt['request_id']);
        Http::assertSentCount(3);
    }

    public function test_explicit_warehouse_is_preserved_in_write_and_readback(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'WH', 'name' => 'WH', 'is_active' => true, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'marketplace:'.$store->id, 'warehouse_id' => $warehouse,
            'platform_location_id' => 'MY-WH-2', 'is_enabled' => true, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*' => Http::sequence()->push($this->item(3, 'MY-WH-2'))->push(['code' => '0'])->push($this->item(8, 'MY-WH-2'))]);

        $this->assertSame(8, $publisher->publish($store, $listing, 8, 'warehouse')['observed_quantity']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update')
            && str_contains((string) ($request->data()['payload'] ?? ''), '<WarehouseCode>MY-WH-2</WarehouseCode>'));
    }

    public function test_dropshipping_only_sku_uses_single_warehouse_payload_despite_store_route(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'WH', 'name' => 'WH', 'is_active' => true, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'marketplace:'.$store->id, 'warehouse_id' => $warehouse,
            'platform_location_id' => 'MY-WH-2', 'is_enabled' => true, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*' => Http::sequence()->push($this->item(0, 'dropshipping'))
            ->push($this->item(0, 'dropshipping'))
            ->push(['code' => '0'])->push($this->item(25, 'dropshipping'))]);

        $this->assertSame(25, $publisher->publish($store, $listing, 25, 'dropshipping')['observed_quantity']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update')
            && str_contains((string) ($request->data()['payload'] ?? ''), '<SellableQuantity>25</SellableQuantity>')
            && ! str_contains((string) ($request->data()['payload'] ?? ''), '<MultiWarehouseInventories>'));
    }

    public function test_strict_facility_can_write_before_its_sku_stock_row_exists_and_requires_exact_readback(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $listing->sync_provider_location_id = 'MY-WH-2';
        $listing->sync_strict_facility = true;
        Http::fake(['*' => Http::sequence()->push(['code' => '0'])
            ->push($this->item(7, 'MY-WH-2'))]);

        $receipt = $publisher->publish($store, $listing, 7, 'strict-facility');
        $this->assertSame('MY-WH-2', $receipt['provider_location_id']);
        $this->assertSame(7, $receipt['observed_quantity']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update')
            && str_contains((string) ($request->data()['payload'] ?? ''), '<WarehouseCode>MY-WH-2</WarehouseCode>'));
    }

    public function test_strict_facility_does_not_confirm_another_warehouse_after_write(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $listing->sync_provider_location_id = 'MY-WH-2';
        $listing->sync_strict_facility = true;
        Http::fake(['*' => Http::sequence()->push(['code' => '0'])
            ->push($this->item(7, 'dropshipping'))]);

        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'strict-facility-missing-readback');
    }

    public function test_strict_dropshipping_readback_selects_dropshipping_after_other_warehouses_exist(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $listing->sync_provider_location_id = 'dropshipping';
        $listing->sync_strict_facility = true;
        Http::fake(['*' => Http::sequence()->push(['code' => '0'])->push(['code' => '0', 'data' => [
            'item_id' => '1001', 'skus' => [['sku_id' => '2001', 'multiWarehouseInventories' => [
                ['warehouseCode' => 'dropshipping', 'sellableQuantity' => '8'],
                ['warehouseCode' => 'MY-WH-2', 'sellableQuantity' => '7'],
            ]]],
        ]])]);

        $receipt = $publisher->publish($store, $listing, 8, 'strict-dropshipping');
        $this->assertSame('dropshipping', $receipt['provider_location_id']);
        $this->assertSame(8, $receipt['observed_quantity']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update')
            && ! str_contains((string) ($request->data()['payload'] ?? ''), '<MultiWarehouseInventories>'));
    }

    public function test_direct_resync_with_no_pinned_warehouse_uses_single_warehouse_payload(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $listing->sync_provider_location_id = null;
        Http::fake(['*' => Http::sequence()->push($this->item(0, 'dropshipping'))
            ->push(['code' => '0'])->push($this->item(25, 'dropshipping'))]);

        $this->assertSame(25, $publisher->publish($store, $listing, 25, 'direct-resync')['observed_quantity']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update')
            && ! str_contains((string) ($request->data()['payload'] ?? ''), '<MultiWarehouseInventories>'));
    }

    public function test_mismatched_multi_warehouse_route_does_not_write(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $warehouse = DB::table('inventory_warehouses')->insertGetId([
            'code' => 'WH', 'name' => 'WH', 'is_active' => true, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_fulfillment_routes')->insert([
            'channel_key' => 'marketplace:'.$store->id, 'warehouse_id' => $warehouse,
            'platform_location_id' => 'WRONG', 'is_enabled' => true, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*' => Http::response(['code' => '0', 'data' => [
            'item_id' => '1001', 'skus' => [['sku_id' => '2001', 'multiWarehouseInventories' => [
                ['warehouseCode' => 'A', 'sellableQuantity' => '1'], ['warehouseCode' => 'B', 'sellableQuantity' => '2'],
            ]]],
        ]])]);

        try {
            $publisher->publish($store, $listing, 25, 'wrong-route');
            $this->fail('A mismatched multi-warehouse route was accepted.');
        } catch (RuntimeException) {
            Http::assertSentCount(2);
            Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/product/stock/sellable/update'));
        }
    }

    public function test_absent_or_ambiguous_warehouse_identity_is_rejected_before_write(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::response(['code' => '0', 'data' => [
            'item_id' => '1001', 'skus' => [['sku_id' => '2001', 'multiWarehouseInventories' => [
                ['warehouseCode' => 'A', 'sellableQuantity' => '1'], ['warehouseCode' => 'B', 'sellableQuantity' => '2'],
            ]]],
        ]])]);

        $this->expectException(RuntimeException::class);
        $publisher->publish($store, $listing, 7, 'ambiguous');
    }

    public function test_auth_expiry_and_mismatched_readback_are_not_confirmed(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::response(['code' => 'IllegalAccessToken', 'request_id' => 'SAFE'], 403)]);
        try {
            $publisher->publish($store, $listing, 7, 'auth');
            $this->fail('Expired authorization was accepted.');
        } catch (StoreInventoryAuthorizationExpired) {
            $this->assertTrue(true);
        }
    }

    public function test_mismatched_readback_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::sequence()->push($this->item(3))->push(['code' => '0', 'request_id' => 'REQ-L'])->push($this->item(6))]);

        try {
            $publisher->publish($store, $listing, 7, 'mismatch');
            $this->fail('Expected an uncertain readback.');
        } catch (StoreInventoryResultUncertain $error) {
            $this->assertTrue($error->diagnostics['write_accepted']);
            $this->assertSame('REQ-L', $error->diagnostics['write_request_id']);
            $this->assertSame(6, $error->diagnostics['observed_quantity']);
            $this->assertSame((string) $store->external_shop_id, $error->diagnostics['provider_shop_id']);
            $this->assertNotEmpty($error->diagnostics['readback_checked_at']);
            $this->assertArrayNotHasKey('access_token', $error->diagnostics);
        }
    }

    public function test_post_write_authorization_failure_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::sequence()->push($this->item(3))->push(['code' => '0'])
            ->push(['code' => 'IllegalAccessToken', 'request_id' => 'SAFE'], 403)]);

        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'auth-after-write');
    }

    public function test_observation_remains_available_with_write_switches_disabled(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        config([
            'marketplace.stock_sync_operations_enabled' => false,
            'marketplace.stock_sync_platforms.lazada' => false,
            'marketplace.stock_sync_canary_identities' => [],
        ]);
        Http::fake(['*' => Http::response($this->item(5))]);

        $this->assertSame(5, $publisher->observe($store, $listing)['observed_quantity']);
    }

    public function test_read_rate_limit_retries_once_but_write_connection_loss_is_never_retried(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return match ($calls) {
                1 => Http::response(['code' => 'SellerCallLimit']),
                2 => Http::response($this->item(3)),
                default => throw new ConnectionException('lost'),
            };
        });

        try {
            $publisher->publish($store, $listing, 7, 'lost-write');
            $this->fail('Unknown write was accepted.');
        } catch (StoreInventoryResultUncertain) {
            $this->assertSame(3, $calls);
        }
    }

    public function test_server_error_after_write_request_is_uncertain(): void
    {
        [$publisher, $store, $listing] = $this->publisher();
        Http::fake(['*' => Http::sequence()->push($this->item(3))
            ->push(['message' => 'temporary provider failure'], 503)]);

        $this->expectException(StoreInventoryResultUncertain::class);
        $publisher->publish($store, $listing, 7, 'server-error-after-send');
    }

    private function publisher(): array
    {
        $store = (new StoreInventoryFixture)->store('lazada', strtoupper(bin2hex(random_bytes(3))));
        DB::table('marketplace_stores')->where('id', $store->id)->update(['external_shop_id' => '3001', 'environment' => 'live']);
        MarketplaceStoreToken::create([
            'store_id' => $store->id, 'access_token' => 'token', 'refresh_token' => 'refresh',
            'access_expires_at' => now()->addHour(), 'refresh_expires_at' => now()->addDay(),
            'application_id' => '123', 'credential_source' => 'oauth',
        ]);
        $store = DB::table('marketplace_stores')->find($store->id);
        $listing = (object) ['id' => 51, 'store_id' => $store->id, 'external_product_id' => '1001', 'external_variant_id' => '2001'];
        config(['marketplace.stock_sync_canary_identities' => ["lazada:{$store->id}:51"]]);

        return [app(LazadaStoreInventoryPublisher::class), $store, $listing];
    }

    private function item(int $quantity, ?string $warehouse = null): array
    {
        $sku = ['sku_id' => '2001'];
        if ($warehouse === null) {
            $sku['sellableQuantity'] = (string) $quantity;
        } else {
            $sku['multiWarehouseInventories'] = [['warehouseCode' => $warehouse, 'sellableQuantity' => (string) $quantity]];
        }

        return ['code' => '0', 'data' => ['item_id' => '1001', 'skus' => [$sku]]];
    }
}
