<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1AcceptanceCatalogAndCartPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_drawer_shows_the_entire_sku_that_was_added_to_the_cart(): void
    {
        $product = Product::query()->create([
            'handle' => 'p1-cart-sku',
            'title' => 'P1 Cart SKU Fixture',
            'base_price' => 22000,
            'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-SOLIS-WAL-STD',
            'title' => 'Rich Walnut',
            'price' => 22000,
            'stock' => 1,
            'enabled' => true,
        ]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->get(route('cart.drawer'))
            ->assertOk()
            ->assertSee('SKU-SOLIS-WAL-STD', false)
            ->assertDontSee('SKU-SOLIS-WAL-S</span>', false);
    }

    public function test_catalog_never_renders_a_negative_sale_percentage_when_compare_price_is_invalid(): void
    {
        $product = Product::query()->create([
            'handle' => 'p1-invalid-sale',
            'title' => 'Invalid Sale Fixture',
            'base_price' => 89900,
            'compare_at_price' => 24900,
            'status' => 'active',
        ]);
        $product->variants()->create([
            'sku' => 'INVALID-SALE-001',
            'title' => 'Default',
            'price' => 89900,
            'compare_at_price' => 107900,
            'stock' => 1,
            'enabled' => true,
        ]);

        $this->get(route('storefront.index'))
            ->assertOk()
            ->assertDontSee('SAVE -261%', false);
    }
}
