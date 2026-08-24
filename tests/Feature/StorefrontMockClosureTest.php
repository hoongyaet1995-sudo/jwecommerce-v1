<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontMockClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_page_uses_the_order_currency_snapshot_for_every_amount(): void
    {
        StoreSetting::query()->create(['key' => 'currencies', 'value' => ['data' => [[
            'id' => 'MYR', 'code' => 'MYR', 'title' => 'Malaysian Ringgit',
            'symbolLeft' => 'RM ', 'symbolRight' => '', 'decimalPlaces' => 2,
            'exchangeRate' => 1, 'isDefault' => true, 'isEnabled' => true,
        ]]]]);
        $order = Order::query()->create([
            'number' => 'JW-CLOSURE-001',
            'email' => 'buyer@example.test',
            'currency' => 'MYR',
            'currency_exchange_rate' => 1,
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 500,
            'shipping_amount' => 1200,
            'tax_amount' => 0,
            'total' => 10700,
        ]);
        $order->items()->create([
            'sku' => 'CLOSURE-1',
            'title' => 'Operational item',
            'variant_title' => 'Default',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->withSession(['last_order_id' => $order->id])
            ->get('/order/success')
            ->assertOk()
            ->assertSee('RM 107.00')
            ->assertDontSee('$107.00 USD', false);
    }

    public function test_storefront_hides_unverifiable_marketing_and_rating_claims(): void
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Operational product',
            'handle' => 'operational-product',
            'base_price' => 10000,
            'status' => 'active',
        ]);
        $product->variants()->create(['sku' => 'OP-1', 'title' => 'Default', 'price' => 10000, 'stock' => 5, 'enabled' => true]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/image-not-found.png', 'position' => 0]);

        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('COMPLIMENTARY SHIPPING')
            ->assertDontSee('WELCOME10')
            ->assertDontSee('4.8 Stars & Up')
            ->assertDontSee('Free Global Delivery')
            ->assertDontSee('256-bit SSL encrypted Stripe');

        $this->get('/account/register')
            ->assertOk()
            ->assertDontSee('100 welcome reward points')
            ->assertDontSee('member pricing');
    }
}
