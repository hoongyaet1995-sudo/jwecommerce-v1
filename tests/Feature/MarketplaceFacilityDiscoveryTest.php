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

    public function test_shopee_partial_pickup_warehouse_api_is_not_registered_as_full_stock_topology(): void
    {
        $store = (new StoreInventoryFixture)->store('shopee', 'FACILITY');
        Http::preventStrayRequests();

        $result = app(FacilityDiscovery::class)->refresh((int) $store->id);

        $this->assertSame('verification_pending', $result['status']);
        $this->assertSame('complete_location_api_unverified', $result['error_code']);
        Http::assertNothingSent();
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
