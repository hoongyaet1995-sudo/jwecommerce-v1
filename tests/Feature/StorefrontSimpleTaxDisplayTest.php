<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\TaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSimpleTaxDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_hides_tax_without_an_active_class_and_labels_an_active_zero_rate(): void
    {
        $variant = $this->variant();
        $this->withSession(['cart' => [$variant->id => 1]])
            ->get('/checkout')
            ->assertOk()
            ->assertDontSee('data-store-tax-row', false);

        TaxClass::factory()->create(['name' => 'Zero Rate', 'rate_percent' => '0.0000', 'is_active' => true, 'active_slot' => 1]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->get('/checkout')
            ->assertOk()
            ->assertSee('data-store-tax-row', false)
            ->assertSee('Zero Rate (0%)');
    }

    private function variant(): \App\Models\ProductVariant
    {
        $category = Category::query()->create(['name' => 'Tax display '.str()->random(8), 'slug' => str()->random(12), 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Tax display product', 'handle' => str()->random(14), 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);

        return $product->variants()->create(['sku' => str()->random(12), 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
    }
}
