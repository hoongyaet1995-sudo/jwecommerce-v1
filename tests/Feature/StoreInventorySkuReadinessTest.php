<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\OverviewReader;
use App\Services\Inventory\StoreManagement\StoreSkuSyncReadiness;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventorySkuReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        config([
            'marketplace.stock_sync_operations_enabled' => true,
            'marketplace.stock_sync_platforms.shopee' => true,
            'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => true,
        ]);
    }

    public function test_one_mapped_counted_variant_is_ready_despite_709_unrelated_unmapped_listings(): void
    {
        $case = $this->mappedCase();
        foreach (range(1, 709) as $index) {
            $this->listing($case['store']->id, 'UNRELATED-'.$index, 'active');
        }

        $result = app(StoreSkuSyncReadiness::class)->read($case['store']->id, $case['variant']->id);

        $this->assertTrue($result['ready']);
        $this->assertSame('ready', $result['effective_state']);
        $this->assertSame(709, $result['setup_info']['unmapped_active_listings']);
        $this->assertSame([], $result['blockers']);
    }

    public function test_mapping_and_inventory_failures_are_reported_per_sku(): void
    {
        $case = $this->mappedCase();
        DB::table('marketplace_variant_mappings')->delete();
        $this->assertBlocker($case, 'sku_not_mapped');

        $first = $this->listing($case['store']->id, 'DUPLICATE');
        $this->map($first, $case['store']->id, $case['variant']->id);
        DB::table('inventory_balances')->where('id', $case['balance']['id'])->update(['counted_at' => null]);
        $this->assertBlocker($case, 'initial_count_required');

        DB::table('inventory_balances')->where('id', $case['balance']['id'])->update([
            'counted_at' => now(), 'physical_stock' => 2, 'reserved_stock' => 3,
        ]);
        $this->assertBlocker($case, 'reservation_conflict');
    }

    public function test_database_prevents_duplicate_store_sku_mappings(): void
    {
        $case = $this->mappedCase();
        $second = $this->listing($case['store']->id, 'DUPLICATE');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->map($second, $case['store']->id, $case['variant']->id);
    }

    public function test_authorization_policy_and_canary_failures_are_explicit(): void
    {
        $case = $this->mappedCase();
        DB::table('marketplace_store_tokens')->where('store_id', $case['store']->id)->update(['access_expires_at' => now()->subMinute()]);
        $this->assertBlocker($case, 'authorization_expired');

        DB::table('marketplace_store_tokens')->where('store_id', $case['store']->id)->update(['access_expires_at' => now()->addHour()]);
        config(['marketplace.stock_sync_platforms.shopee' => false]);
        $this->assertBlocker($case, 'platform_stock_sync_disabled');

        config(['marketplace.stock_sync_platforms.shopee' => true, 'marketplace.stock_sync_canary_identities' => []]);
        $this->assertBlocker($case, 'canary_not_allowlisted');
    }

    public function test_expired_access_token_is_ready_when_a_valid_refresh_token_exists(): void
    {
        $case = $this->mappedCase();
        DB::table('marketplace_store_tokens')->where('store_id', $case['store']->id)->update([
            'access_expires_at' => now()->subMinute(), 'refresh_token' => 'encrypted-refresh', 'refresh_expires_at' => now()->addHour(),
        ]);

        $this->assertTrue(app(StoreSkuSyncReadiness::class)->read($case['store']->id, $case['variant']->id)['ready']);
    }

    public function test_overview_uses_canonical_listing_participation_readiness(): void
    {
        $case = $this->mappedCase();
        DB::table('marketplace_listing_variants')->where('id', $case['listingId'])
            ->update(['observed_data' => json_encode(['status' => 'archived'])]);

        $overview = app(OverviewReader::class)->read();
        $row = collect($overview['data'])->first(fn (array $row): bool => $row['variant_id'] === $case['variant']->id && $row['store_id'] === $case['store']->id);

        $this->assertFalse($row['sync_readiness']['ready']);
        $this->assertContains('listing_not_participating', array_column($row['sync_readiness']['blockers'], 'code'));
    }

    private function mappedCase(): array
    {
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('shopee', strtoupper(bin2hex(random_bytes(3))));
        $variant = $fixture->sku()['variant'];
        $listingId = $this->listing($store->id, 'PRIMARY');
        $this->map($listingId, $store->id, $variant->id);
        $balance = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 12, 'reserved_stock' => 2, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('marketplace_store_tokens')->insert([
            'store_id' => $store->id, 'access_token' => 'encrypted-placeholder', 'refresh_token' => null,
            'access_expires_at' => now()->addHour(), 'refresh_expires_at' => null, 'application_id' => 'test',
            'credential_source' => 'oauth', 'test_lease_expires_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        config(['marketplace.stock_sync_canary_identities' => ["shopee:{$store->id}:{$listingId}"]]);

        return compact('store', 'variant', 'listingId', 'balance');
    }

    private function listing(int $storeId, string $externalId, string $status = 'active'): int
    {
        return DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $storeId, 'external_product_id' => $externalId, 'external_variant_id' => $externalId,
            'observed_data' => json_encode(['status' => $status]), 'fetched_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function map(int $listingId, int $storeId, int $variantId): void
    {
        DB::table('marketplace_variant_mappings')->insert([
            'listing_variant_id' => $listingId, 'store_id' => $storeId, 'product_variant_id' => $variantId,
            'actor_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertBlocker(array $case, string $code): void
    {
        $result = app(StoreSkuSyncReadiness::class)->read($case['store']->id, $case['variant']->id);
        $this->assertFalse($result['ready']);
        $this->assertContains($code, array_column($result['blockers'], 'code'));
    }
}
