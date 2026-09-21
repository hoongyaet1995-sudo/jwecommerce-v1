<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\StockSyncPolicy;
use Tests\TestCase;

class StoreInventoryStockSyncPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketplace.stock_sync_operations_enabled' => true,
            'marketplace.stock_sync_platforms.shopee' => true,
            'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => true,
            'marketplace.stock_sync_canary_identities' => ['shopee:10:200', 'lazada:11:201'],
        ]);
    }

    public function test_master_switch_is_mandatory(): void
    {
        config(['marketplace.stock_sync_operations_enabled' => false]);

        $result = $this->review('shopee', 10, 200);

        $this->assertFalse($result['allowed']);
        $this->assertSame('stock_sync_disabled', $result['code']);
    }

    public function test_each_supported_platform_has_an_independent_switch(): void
    {
        config(['marketplace.stock_sync_platforms.shopee' => false]);
        $this->assertSame('platform_stock_sync_disabled', $this->review('shopee', 10, 200)['code']);

        config(['marketplace.stock_sync_platforms.shopee' => true, 'marketplace.stock_sync_platforms.lazada' => false]);
        $this->assertSame('platform_stock_sync_disabled', $this->review('lazada', 11, 201)['code']);
    }

    public function test_canary_must_be_present_and_must_match_the_persisted_identity(): void
    {
        $this->assertSame('canary_not_allowlisted', $this->review('shopee', 10, 999)['code']);

        $result = $this->review('shopee', 10, 200);
        $this->assertTrue($result['allowed']);
        $this->assertSame('ready', $result['code']);
    }

    public function test_disconnected_store_and_unsupported_platform_are_rejected(): void
    {
        $this->assertSame('store_not_connected', $this->review('shopee', 10, 200, 'setup_required')['code']);
        $this->assertSame('platform_not_supported', $this->review('tiktok', 10, 200)['code']);
    }

    public function test_malformed_duplicate_or_cross_platform_canary_configuration_fails_closed(): void
    {
        foreach ([
            ['bad-entry'],
            ['shopee:10:200', 'shopee:10:200'],
            ['lazada:10:200'],
        ] as $identities) {
            config(['marketplace.stock_sync_canary_identities' => $identities]);
            $this->assertSame('canary_configuration_invalid', $this->review('shopee', 10, 200)['code']);
        }
    }

    private function review(string $platform, int $storeId, int $listingId, string $status = 'connected'): array
    {
        return app(StockSyncPolicy::class)->review(
            (object) ['id' => $storeId, 'platform' => $platform, 'status' => $status],
            (object) ['id' => $listingId, 'store_id' => $storeId],
        );
    }
}
