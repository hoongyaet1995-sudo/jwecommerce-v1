<?php

namespace Tests\Unit;

use App\Services\Marketplace\MarketplaceOrderImage;
use PHPUnit\Framework\TestCase;

class MarketplaceOrderImageTest extends TestCase
{
    public function test_extracts_provider_thumbnail_without_accepting_arbitrary_hosts(): void
    {
        $lazada = ['order_items' => [['product_main_image' => 'https://sg-test-11.slatic.net/p/image.jpg']]];
        $shopee = ['item_list' => [['image_info' => ['image_url' => 'https://cf.shopee.com.my/file/abc']]]];
        $this->assertSame('https://sg-test-11.slatic.net/p/image.jpg', MarketplaceOrderImage::fromPayload($lazada, 'lazada'));
        $this->assertSame('https://cf.shopee.com.my/file/abc', MarketplaceOrderImage::fromPayload($shopee, 'shopee'));
        $this->assertNull(MarketplaceOrderImage::fromPayload(['order_items' => [['product_main_image' => 'https://example.com/p.jpg']]], 'lazada'));
    }
}
