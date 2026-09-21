<?php

namespace Tests\Feature;

use App\Services\Inventory\StoreManagement\LazadaStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\MarketplaceStoreInventoryPublisher;
use App\Services\Inventory\StoreManagement\ShopeeStoreInventoryPublisher;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreInventoryProviderRoutingTest extends TestCase
{
    public function test_routes_shopee_and_lazada_by_persisted_platform(): void
    {
        $shopee = Mockery::mock(ShopeeStoreInventoryPublisher::class);
        $lazada = Mockery::mock(LazadaStoreInventoryPublisher::class);
        $listing = (object) ['id' => 1];
        $shopee->shouldReceive('publish')->once()->andReturn(['provider' => 'shopee']);
        $lazada->shouldReceive('publish')->once()->andReturn(['provider' => 'lazada']);
        $router = new MarketplaceStoreInventoryPublisher($shopee, $lazada);

        $this->assertSame('shopee', $router->publish((object) ['platform' => 'shopee'], $listing, 1, 'a')['provider']);
        $this->assertSame('lazada', $router->publish((object) ['platform' => 'lazada'], $listing, 1, 'b')['provider']);
    }

    public function test_unsupported_platform_fails_before_any_provider_call(): void
    {
        $shopee = Mockery::mock(ShopeeStoreInventoryPublisher::class);
        $lazada = Mockery::mock(LazadaStoreInventoryPublisher::class);
        $shopee->shouldNotReceive('publish');
        $lazada->shouldNotReceive('publish');

        $this->expectException(RuntimeException::class);
        (new MarketplaceStoreInventoryPublisher($shopee, $lazada))->publish(
            (object) ['platform' => 'tiktok'], (object) ['id' => 1], 1, 'event',
        );
    }
}
