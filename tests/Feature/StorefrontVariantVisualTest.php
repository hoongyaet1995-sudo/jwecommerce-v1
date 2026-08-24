<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontVariantVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_navigation_uses_the_persisted_shop_name(): void
    {
        StoreSetting::query()->create([
            'key' => 'shop_name',
            'value' => ['data' => 'JW Studio Commerce'],
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('>JW Studio Commerce</span>', false)
            ->assertDontSee('>ATELIER</span>', false);
    }

    public function test_pdp_exposes_persisted_option_visuals_for_the_source_faithful_selector(): void
    {
        $product = Product::query()->create([
            'handle' => 'visual-monitor',
            'title' => 'Visual Monitor',
            'base_price' => 29900,
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
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/hero.jpg', 'position' => 0]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'VIS-GREY', 'title' => 'Night Grey',
            'options' => ['Finish' => 'Night Grey'], 'price' => 29900, 'stock' => 12,
            'enabled' => true, 'visual_type' => 'color', 'color_code' => '#64748B',
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'VIS-WHITE', 'title' => 'Studio White',
            'options' => ['Finish' => 'Studio White'], 'price' => 31900, 'stock' => 0,
            'enabled' => true, 'visual_type' => 'image', 'option_image' => '/images/catalog/white.jpg',
        ]);
        $header = Menu::query()->create(['key' => 'header', 'name' => 'Storefront Header']);
        $menu = MenuItem::query()->create([
            'menu_id' => $header->id, 'label' => 'Catalog', 'url' => '/shop', 'position' => 1,
            'is_active' => true, 'display_style' => 'megamenu',
        ]);
        MenuItem::query()->create([
            'menu_id' => $header->id, 'parent_id' => $menu->id, 'label' => 'Audio', 'url' => '/categories/audio',
            'position' => 1, 'is_active' => true,
        ]);

        $this->get('/products/visual-monitor')
            ->assertOk()
            ->assertSee('data-storefront-variant-selector', false)
            ->assertSee('data-pdp-option-selector', false)
            ->assertSee('data-pdp-option-name="Finish"', false)
            ->assertSee('data-pdp-option-visual="color"', false)
            ->assertSee('data-pdp-option-visual="image"', false)
            ->assertSee('aria-label="Studio White - Out of Stock"', false)
            ->assertSee('top-[96px]', false)
            ->assertDontSee('top-[104px]', false)
            ->assertDontSee('Configuration')
            ->assertDontSee('Standard atelier specification');
    }

    public function test_pdp_thumbnails_expose_the_client_side_image_switching_contract(): void
    {
        $product = Product::query()->create([
            'handle' => 'thumbnail-switching-product',
            'title' => 'Thumbnail Switching Product',
            'base_price' => 29900,
            'status' => 'active',
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => '/images/catalog/thumbnail-primary.jpg',
            'position' => 0,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => '/images/catalog/thumbnail-secondary.jpg',
            'position' => 1,
        ]);

        $this->get('/products/thumbnail-switching-product')
            ->assertOk()
            ->assertSee('data-pdp-thumbnail-switch', false)
            ->assertSee('data-pdp-image-path="/images/catalog/thumbnail-primary.jpg"', false)
            ->assertSee('data-pdp-image-path="/images/catalog/thumbnail-secondary.jpg"', false);
    }

    public function test_plp_exposes_a_non_navigating_source_variant_preview_for_color_and_image_variants(): void
    {
        $product = Product::query()->create([
            'handle' => 'visual-card', 'title' => 'Visual Card', 'base_price' => 19900,
            'status' => 'active', 'has_variants' => true,
        ]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => '/images/catalog/card.jpg', 'position' => 0]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'CARD-COLOR', 'title' => 'Night Grey',
            'price' => 19900, 'stock' => 8, 'enabled' => true,
            'visual_type' => 'color', 'color_code' => '#64748B',
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'CARD-IMAGE', 'title' => 'Studio White',
            'price' => 20900, 'stock' => 2, 'enabled' => true,
            'visual_type' => 'image', 'option_image' => '/images/catalog/white.jpg',
        ]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-product-card-variant-preview', false)
            ->assertSee('data-card-variant-visual="color"', false)
            ->assertSee('data-card-variant-visual="image"', false)
            ->assertSee('data-card-variant-id', false);
    }

    public function test_pdp_renders_one_source_faithful_selector_for_each_persisted_option_dimension(): void
    {
        $product = Product::query()->create([
            'handle' => 'dimension-selector-product',
            'title' => 'Dimension Selector Product',
            'base_price' => 29900,
            'status' => 'active',
            'has_variants' => true,
            'options' => [
                [
                    'id' => 'color',
                    'name' => 'Color',
                    'displayType' => 'color_palette',
                    'values' => ['Obsidian Black', 'Lunar Silver'],
                    'valueVisuals' => [
                        'Obsidian Black' => ['visualType' => 'color', 'colorCode' => '#111827'],
                        'Lunar Silver' => ['visualType' => 'color', 'colorCode' => '#64748B'],
                    ],
                ],
                [
                    'id' => 'material',
                    'name' => 'Material',
                    'displayType' => 'option_image',
                    'values' => ['Matte Titanium', 'Natural Leather', 'Sandstone Ceramic'],
                    'valueVisuals' => [
                        'Matte Titanium' => ['visualType' => 'image', 'optionImage' => '/images/catalog/titanium.jpg'],
                        'Natural Leather' => ['visualType' => 'none'],
                        'Sandstone Ceramic' => ['visualType' => 'image', 'optionImage' => '/images/catalog/ceramic.jpg'],
                    ],
                ],
                [
                    'id' => 'edition',
                    'name' => 'Edition',
                    'displayType' => 'dropdown',
                    'values' => ['Standard Audio', 'Studio Master ANC'],
                    'valueVisuals' => [
                        'Standard Audio' => ['visualType' => 'none'],
                        'Studio Master ANC' => ['visualType' => 'none'],
                    ],
                ],
            ],
        ]);

        foreach ([
            ['Obsidian Black', 'Matte Titanium', 'Standard Audio', 'DIM-BLK-TI', 12],
            ['Obsidian Black', 'Natural Leather', 'Studio Master ANC', 'DIM-BLK-LEATHER', 0],
            ['Lunar Silver', 'Matte Titanium', 'Standard Audio', 'DIM-SILVER-TI', 4],
        ] as [$color, $material, $edition, $sku, $stock]) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $sku,
                'title' => "$color / $material",
                'options' => ['Color' => $color, 'Material' => $material, 'Edition' => $edition],
                'price' => 29900,
                'stock' => $stock,
                'enabled' => true,
            ]);
        }

        $response = $this->get('/products/dimension-selector-product')->assertOk();
        $html = $response->getContent();

        $this->assertSame(3, substr_count($html, 'data-pdp-option-selector'));
        $response
            ->assertSee('data-pdp-option-name="Color"', false)
            ->assertSee('data-pdp-option-name="Material"', false)
            ->assertSee('data-pdp-option-name="Edition"', false)
            ->assertSee('data-pdp-option-visual="color"', false)
            ->assertSee('data-pdp-option-visual="image"', false)
            ->assertSee('data-pdp-option-visual="pill"', false)
            ->assertSee('data-pdp-option-display-mode="dropdown"', false)
            ->assertSee('data-pdp-option-dropdown', false)
            ->assertSee('Obsidian Black', false)
            ->assertSee('Matte Titanium', false)
            ->assertSee('Natural Leather', false)
            ->assertSee('/images/catalog/titanium.jpg', false);
    }

    public function test_pdp_normalizes_unambiguous_legacy_variant_titles_against_persisted_dimensions(): void
    {
        $product = Product::query()->create([
            'handle' => 'legacy-dimension-selector',
            'title' => 'Legacy Dimension Selector',
            'base_price' => 38900,
            'status' => 'active',
            'has_variants' => true,
            'options' => [
                [
                    'id' => 'color',
                    'name' => 'Color',
                    'displayType' => 'color_palette',
                    'values' => ['Obsidian Black', 'Lunar Silver'],
                    'valueVisuals' => [],
                ],
                [
                    'id' => 'material',
                    'name' => 'Material',
                    'displayType' => 'option_image',
                    'values' => ['Matte Titanium', 'Natural Leather'],
                    'valueVisuals' => [],
                ],
            ],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'LEGACY-BLK-TI',
            'title' => 'Obsidian Black / Matte Titanium',
            'options' => [],
            'price' => 38900,
            'stock' => 12,
            'enabled' => true,
        ]);

        $html = $this->get('/products/legacy-dimension-selector?variant='.$variant->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"options":{"Color":"Obsidian Black","Material":"Matte Titanium"}', $html);
        $this->assertStringContainsString('data-pdp-option-name="Color"', $html);
        $this->assertStringContainsString('data-pdp-option-name="Material"', $html);
        $this->assertStringNotContainsString('aria-disabled="true" aria-label="Obsidian Black"', $html);
    }
}
