<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\StoreManagement\FacilityDiscovery;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.lazada.enabled' => true, 'marketplace.lazada.app_key' => '123',
            'marketplace.lazada.app_secret' => 'secret']);
    }

    public function test_lazada_complete_warehouse_read_registers_active_facilities(): void
    {
        $store = $this->lazadaStore();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => '0', 'result' => ['success' => true, 'module' => [
            ['code' => 'WH-A', 'name' => 'Kuala Lumpur', 'status' => 'ACTIVE'],
            ['code' => 'WH-B', 'name' => 'Penang', 'status' => 'ACTIVE'],
        ]]])]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('multi_warehouse', $result['topology']);
        $this->assertSame(['WH-A', 'WH-B'], array_column($result['facilities'], 'provider_id'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/seller/warehouse/get')
            && ($request['addressTypes'] ?? null) === '["warehouse"]');
    }

    public function test_lazada_official_test_warehouse_shape_registers_eight_distinct_codes_without_stock(): void
    {
        $store = $this->lazadaStore();
        $codes = ['dropshipping'];
        foreach (range(10003, 10009) as $number) {
            $codes[] = 'MY4NA1T7CK-WH-'.$number;
        }
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => '0', 'result' => ['success' => true, 'module' => array_map(
            fn (string $code): array => [
                'code' => $code, 'name' => 'Test warehouse '.$code,
                'detailAddress' => 'Not an inventory count', 'needToUpdate' => false,
                'defaultAddress' => false, 'status' => 'ACTIVE',
            ], $codes,
        )]])]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('multi_warehouse', $result['topology']);
        sort($codes);
        $this->assertSame($codes, array_column($result['facilities'], 'provider_id'));
        $this->assertFalse($result['stock_operations_ready']);
        $this->assertSame(0, DB::table('inventory_balances')->count());
    }

    public function test_incomplete_lazada_read_preserves_last_list_and_marks_pending(): void
    {
        $store = $this->lazadaStore();
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-OLD', 'name' => 'Old']]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => '0', 'result' => ['success' => true, 'module' => [
            ['code' => 'WH-A', 'name' => 'A'],
        ]]])]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verification_pending', $result['status']);
        $this->assertSame('WH-OLD', $result['facilities'][0]['provider_id']);
        $this->assertSame('provider_response_incomplete', $result['error_code']);
    }

    public function test_shopee_unavailable_discovery_uses_existing_default_inventory(): void
    {
        $store = (new StoreInventoryFixture)->store('shopee', 'FACILITY');
        Http::preventStrayRequests();

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('no_warehouse', $result['topology']);
        $this->assertSame('default_inventory_fallback', $result['error_code']);
        $this->assertSame([], $result['facilities']);
        Http::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shopeeDiscoveryResponses')]
    public function test_shopee_discovery_uses_only_returned_locations_or_default_inventory(array $payload, int $httpStatus, int $expectedLocations): void
    {
        $store = (new StoreInventoryFixture)->store('shopee', 'LOCATIONS');
        config(['marketplace.multiwarehouse_operations_enabled' => true]);
        DB::table('inventory_configuration')->updateOrInsert(['id' => 1], ['operating_mode' => 'independent_stores']);
        $tokens = \Mockery::mock(\App\Services\Marketplace\ShopeeTokenManager::class);
        $tokens->shouldReceive('accessToken')->with((int) $store->id)->once()->andReturn('test-token');
        $this->app->instance(\App\Services\Marketplace\ShopeeTokenManager::class, $tokens);
        // Discovery resolves the store application before calling the client.
        DB::table('marketplace_stores')->where('id', $store->id)->update(['environment' => 'sandbox', 'external_shop_id' => '456']);
        config(['marketplace.shopee.enabled' => true, 'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123', 'marketplace.shopee.partner_key' => 'secret',
            'marketplace.shopee.callback_url' => 'https://example.test/callback',
            'marketplace.shopee.applications.sandbox' => ['partner_id' => '123', 'partner_key' => 'secret',
                'callback_url' => 'https://example.test/callback']]);
        MarketplaceStoreToken::create(['store_id' => $store->id, 'application_id' => '123',
            'access_token' => 'test-token', 'refresh_token' => 'test-refresh']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($payload, $httpStatus)]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verified', $result['status']);
        $this->assertCount($expectedLocations, $result['facilities']);
        $this->assertSame($expectedLocations ? 'multi_warehouse' : 'no_warehouse', $result['topology']);
        if ($expectedLocations) {
            $this->assertSame(['MY-A', 'MY-B'], array_column($result['facilities'], 'provider_id'));
            $this->assertNotNull($result['facilities'][0]['inventory_warehouse_id']);
            $this->assertNotEquals($result['facilities'][0]['inventory_warehouse_id'], $result['facilities'][1]['inventory_warehouse_id']);
        }
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/shop/get_warehouse_detail'));
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public static function shopeeDiscoveryResponses(): array
    {
        return [
            'locations without address metadata' => [['error' => '', 'response' => [
                ['location_id' => 'MY-A', 'warehouse_name' => 'A'], ['location_id' => 'MY-B'],
            ]], 200, 2],
            'empty' => [['error' => '', 'response' => []], 200, 0],
            'not whitelisted' => [['error' => 'warehouse.error_not_in_whitelist', 'response' => null], 200, 0],
            'unavailable' => [['error' => 'unavailable'], 503, 0],
            'malformed' => [['error' => '', 'response' => [['warehouse_id' => 5]]], 200, 0],
            'unauthorized' => [['error' => 'error_auth'], 401, 0],
        ];
    }

    public function test_default_fallback_preserves_the_store_ledger_and_allows_count_intake_and_adjustment(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('shopee', 'DEFAULT-ACTIONS');
        $variant = $fixture->sku()['variant'];
        $balance = app(\App\Services\Inventory\StoreManagement\AccountResolver::class)
            ->balance((int) $variant->id, 'marketplace:'.$store->id);
        Http::preventStrayRequests();
        app(FacilityDiscovery::class)->refresh((int) $store->id);
        $actor = \App\Models\User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $service = app(\App\Services\Inventory\StoreManagement\ReviewService::class);
        foreach ([['count', ['physical_count' => 10], 10], ['intake', ['quantity' => 5], 15],
            ['correction', ['quantity_delta' => -3], 12]] as [$kind, $input, $expected]) {
            $review = $service->create($actor, $kind, $input + ['balance_id' => $balance['id'], 'reason' => 'Stock check']);
            $service->confirm($actor, $review['id'], 'default-'.$kind);
            $this->assertSame($expected, (int) DB::table('inventory_balances')->where('id', $balance['id'])->value('physical_stock'));
        }
        app(FacilityDiscovery::class)->refresh((int) $store->id);
        $this->assertDatabaseCount('inventory_balances', 1);
        $this->assertSame(12, (int) DB::table('inventory_balances')->where('id', $balance['id'])->value('physical_stock'));
    }

    public function test_lazada_expired_authorization_keeps_previous_facility_read_only(): void
    {
        $store = $this->lazadaStore();
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-OLD', 'name' => 'Old']]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => 'InvalidAccessToken'], 200)]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verification_pending', $result['status']);
        $this->assertSame('authorization_expired', $result['error_code']);
        $this->assertSame('WH-OLD', $result['facilities'][0]['provider_id']);
    }

    public function test_duplicate_lazada_codes_cannot_replace_confirmed_topology(): void
    {
        $store = $this->lazadaStore();
        app(FacilityRegistry::class)->confirm((int) $store->id, [['provider_id' => 'WH-OLD', 'name' => 'Old']]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => '0', 'result' => ['success' => true, 'module' => [
            ['code' => 'WH-A', 'name' => 'A', 'status' => 'ACTIVE'],
            ['code' => 'WH-A', 'name' => 'Duplicate', 'status' => 'ACTIVE'],
        ]]])]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verification_pending', $result['status']);
        $this->assertSame('provider_response_incomplete', $result['error_code']);
        $this->assertSame('WH-OLD', $result['facilities'][0]['provider_id']);
    }

    public function test_explicit_empty_lazada_warehouse_list_is_no_warehouse(): void
    {
        $store = $this->lazadaStore();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['code' => '0', 'result' => ['success' => true, 'module' => []]])]);

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verified', $result['status']);
        $this->assertSame('no_warehouse', $result['topology']);
        $this->assertSame([], $result['facilities']);
    }

    private function lazadaStore(): object
    {
        $store = (new StoreInventoryFixture)->store('lazada', 'FACILITY');
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'external_shop_id' => '3001', 'environment' => 'live',
        ]);
        MarketplaceStoreToken::create([
            'store_id' => $store->id, 'access_token' => 'fixture-token', 'refresh_token' => 'fixture-refresh',
            'access_expires_at' => now()->addHour(), 'refresh_expires_at' => now()->addDay(),
            'application_id' => '123', 'credential_source' => 'oauth',
        ]);

        return DB::table('marketplace_stores')->find($store->id);
    }
}
