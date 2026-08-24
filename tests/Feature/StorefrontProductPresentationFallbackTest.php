<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductPresentationFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdp_and_plp_render_the_managed_image_not_found_asset_when_a_product_has_no_media(): void
    {
        $product = Product::query()->create([
            'handle' => 'no-production-media',
            'title' => 'No Production Media',
            'base_price' => 19900,
            'status' => 'active',
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'NO-MEDIA-001',
            'title' => 'Standard',
            'price' => 19900,
            'stock' => 10,
            'enabled' => true,
        ]);

        $this->get('/products/no-production-media')
            ->assertOk()
            ->assertSee('/images/image-not-found.png', false);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('/images/image-not-found.png', false);
    }

    public function test_pdp_marks_only_the_current_sku_values_as_selected_in_the_selector_contract(): void
    {
        $product = Product::query()->create([
            'handle' => 'selector-state-contract',
            'title' => 'Selector State Contract',
            'base_price' => 19900,
            'status' => 'active',
            'has_variants' => true,
            'options' => [
                ['id' => 'color', 'name' => 'Color', 'displayType' => 'pills', 'values' => ['Blue', 'Red'], 'valueVisuals' => []],
                ['id' => 'size', 'name' => 'Size', 'displayType' => 'pills', 'values' => ['3cm', '4cm'], 'valueVisuals' => []],
            ],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'STATE-BLUE-3',
            'title' => 'Blue / 3cm',
            'options' => ['Color' => 'Blue', 'Size' => '3cm'],
            'price' => 19900,
            'stock' => 10,
            'enabled' => true,
        ]);
        $selected = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'STATE-RED-4',
            'title' => 'Red / 4cm',
            'options' => ['Color' => 'Red', 'Size' => '4cm'],
            'price' => 19900,
            'stock' => 10,
            'enabled' => true,
        ]);

        $html = $this->get('/products/selector-state-contract?variant='.$selected->id)
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, 'aria-checked="true"'));
        $this->assertStringContainsString('data-pdp-option-value="Red" data-pdp-option-name="Color"', $html);
        $this->assertStringContainsString('data-pdp-option-value="4cm" data-pdp-option-name="Size"', $html);
    }
}
