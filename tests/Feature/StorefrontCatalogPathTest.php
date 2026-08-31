<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_category_has_a_canonical_public_listing_path(): void
    {
        $audio = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $other = Category::query()->create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $audioProduct = $this->activeProduct($audio, 'audio-product', 'Audio Product');
        $this->activeProduct($other, 'other-product', 'Other Product');

        $this->get('/categories/audio')
            ->assertOk()
            ->assertSee('Audio Product')
            ->assertSee('/products/audio-product')
            ->assertDontSee('/products/'.$audioProduct->id)
            ->assertDontSee('Other Product');

        $this->get('/products/audio-product')
            ->assertOk()
            ->assertSee('/categories/audio');

        $this->assertSame('/categories/audio', route('storefront.categories.show', ['category' => $audio->slug], false));
    }

    public function test_inactive_category_is_not_publicly_listed(): void
    {
        Category::query()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->get('/categories/hidden')->assertNotFound();
    }

    public function test_active_collection_has_a_canonical_public_listing_path(): void
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $collection = Collection::query()->create(['title' => 'Featured Audio', 'slug' => 'featured-audio', 'is_active' => true]);
        $featured = $this->activeProduct($category, 'featured-product', 'Featured Product');
        $other = $this->activeProduct($category, 'other-product', 'Other Product');
        $collection->products()->attach($featured);

        $this->get('/collections/featured-audio')
            ->assertOk()
            ->assertSee('Featured Product')
            ->assertSee('/products/featured-product')
            ->assertDontSee('/products/'.$featured->id)
            ->assertDontSee('Other Product');

        $this->assertSame('/collections/featured-audio', route('storefront.collections.show', ['collection' => $collection->slug], false));
    }

    public function test_active_collection_uses_the_shared_storefront_footer(): void
    {
        $collection = Collection::query()->create(['title' => 'Footer Collection', 'slug' => 'footer-collection', 'is_active' => true]);

        $this->get(route('storefront.collections.show', ['collection' => $collection->slug]))
            ->assertOk()
            ->assertSee('© 2026 JW Studio. All rights reserved.', false);
    }

    public function test_catalog_listing_retains_the_google_source_footer_spacing(): void
    {
        $this->get(route('storefront.index'))
            ->assertOk()
            ->assertSee('<main class="pb-20">', false);
    }

    public function test_inactive_collection_is_not_publicly_listed(): void
    {
        Collection::query()->create(['title' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->get('/collections/hidden')->assertNotFound();
    }

    private function activeProduct(Category $category, string $handle, string $title): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'handle' => $handle,
            'title' => $title,
            'base_price' => 19900,
            'status' => 'active',
        ]);
    }
}
