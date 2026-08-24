<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontQuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_plp_marks_multi_sku_products_for_the_source_faithful_quick_add_modal(): void
    {
        $product = $this->multiSkuProduct();

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-quick-add-trigger', false)
            ->assertSee('data-quick-add-product-id="'.$product->id.'"', false)
            ->assertSee('data-quick-add-modal', false)
            ->assertSee('View Full Product Specifications &amp; Lab Tests', false)
            ->assertSee('referrerpolicy="no-referrer"', false)
            ->assertDontSee('Backorder Available')
            ->assertDontSee('Dispatches Today')
            ->assertDontSee('Verified Studio Reviews')
            ->assertDontSee('2-Year Atelier Warranty');
    }

    public function test_cart_store_rejects_an_out_of_stock_sku_without_creating_a_cart_line(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-SOLD-OUT')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant_id');

        $this->assertNull(session('cart'));
    }

    public function test_cart_store_rejects_a_quantity_above_the_real_sku_stock(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-IN-STOCK')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertNull(session('cart'));
    }

    public function test_cart_store_rejects_an_addition_that_would_take_an_existing_line_over_stock(): void
    {
        $product = $this->multiSkuProduct();
        $variant = $product->variants()->where('sku', 'QUICK-IN-STOCK')->firstOrFail();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame([$variant->id => 1], session('cart'));
    }

    private function multiSkuProduct(): Product
    {
        $product = Product::query()->create([
            'handle' => 'quick-add-monitor',
            'title' => 'Quick Add Monitor',
            'subtitle' => 'Precision sound for the studio.',
            'brand' => 'ATELIER AUDIO',
            'description' => 'Quick Add source fixture.',
            'base_price' => 29900,
            'compare_at_price' => 34900,
            'status' => 'active',
            'has_variants' => true,
            'options' => [[
                'id' => 'finish',
                'name' => 'Finish',
                'displayType' => 'color_palette',
                'values' => ['Night Grey', 'Studio White'],
                'valueVisuals' => [
                    'Night Grey' => ['visualType' => 'color', 'colorCode' => '#64748B'],
                    'Studio White' => ['visualType' => 'image', 'optionImage' => '/images/catalog/white.jpg'],
                ],
            ]],
        ]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/hero.jpg', 'alt' => 'Quick Add Monitor', 'position' => 0]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/angle.jpg', 'alt' => 'Quick Add Monitor angle', 'position' => 1]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'QUICK-IN-STOCK',
            'title' => 'Night Grey',
            'options' => ['Finish' => 'Night Grey'],
            'price' => 29900,
            'compare_at_price' => 34900,
            'stock' => 2,
            'enabled' => true,
            'visual_type' => 'color',
            'color_code' => '#64748B',
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'QUICK-SOLD-OUT',
            'title' => 'Studio White',
            'options' => ['Finish' => 'Studio White'],
            'price' => 31900,
            'stock' => 0,
            'enabled' => true,
            'visual_type' => 'image',
            'option_image' => '/images/catalog/white.jpg',
        ]);

        return $product->fresh('variants');
    }
}
