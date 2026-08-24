<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductHandleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_handle_is_derived_from_the_title_and_uniquely_reserved_by_the_server_when_no_url_key_is_supplied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);

        $first = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload($category, 'Studio Headphones', 'HANDLE-001'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'studio-headphones');

        $second = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->payload($category, 'Studio Headphones', 'HANDLE-002'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'studio-headphones-2');

        $this->assertDatabaseHas('products', ['id' => $first->json('data.id'), 'handle' => 'studio-headphones']);
        $this->assertDatabaseHas('products', ['id' => $second->json('data.id'), 'handle' => 'studio-headphones-2']);
    }

    public function test_a_manual_url_key_is_saved_and_a_conflicting_url_key_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/api/catalog/products', $this->payload($category, 'Studio Headphones', 'HANDLE-MANUAL-001', null, 'studio-headphones-pro'))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'studio-headphones-pro');

        $this->actingAs($admin)
            ->postJson('/admin/api/catalog/products', $this->payload($category, 'Another Product', 'HANDLE-MANUAL-002', null, 'studio-headphones-pro'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_product_handle_stays_stable_when_an_existing_product_title_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'handle' => 'studio-headphones', 'title' => 'Studio Headphones',
            'base_price' => 19900, 'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'HANDLE-003', 'title' => 'Studio Headphones', 'options' => [], 'price' => 19900,
            'stock' => 10, 'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api/catalog/products/'.$product->id, $this->payload($category, 'Studio Headphones Mark II', 'HANDLE-003', $variant->id))
            ->assertOk()
            ->assertJsonPath('data.slug', 'studio-headphones');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'handle' => 'studio-headphones', 'title' => 'Studio Headphones Mark II']);
    }

    public function test_a_changed_url_key_redirects_the_previous_product_address_to_the_canonical_address(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'handle' => 'studio-headphones', 'title' => 'Studio Headphones',
            'base_price' => 19900, 'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'HANDLE-004', 'title' => 'Studio Headphones', 'options' => [], 'price' => 19900,
            'stock' => 10, 'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api/catalog/products/'.$product->id, $this->payload($category, 'Studio Headphones', 'HANDLE-004', $variant->id, 'studio-headphones-2026'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'studio-headphones-2026');

        $this->get('/products/studio-headphones?variant='.$variant->id)
            ->assertMovedPermanently()
            ->assertRedirect('/products/studio-headphones-2026?variant='.$variant->id);
    }

    public function test_product_route_generation_uses_the_canonical_handle_instead_of_the_database_id(): void
    {
        $product = Product::query()->create([
            'handle' => 'ps5-pro',
            'title' => 'PS5 Pro',
            'base_price' => 89900,
            'status' => 'active',
        ]);

        $this->assertSame('/products/ps5-pro', route('storefront.products.show', ['handle' => $product->handle], false));
    }

    /** @return array<string, mixed> */
    private function payload(Category $category, string $title, string $sku, ?int $variantId = null, ?string $slug = null): array
    {
        return [
            'title' => $title, 'brand' => 'JW Studio', 'categoryId' => $category->id, 'collectionIds' => [],
            'slug' => $slug,
            'summary' => 'Precision sound.', 'descriptionHtml' => '<p>Precision sound.</p>', 'images' => [], 'status' => 'active',
            'hasVariants' => false, 'basePrice' => 199, 'baseCompareAtPrice' => null, 'featured' => false,
            'options' => [], 'tags' => [], 'seoTitle' => null, 'seoDescription' => null,
            'variants' => [[
                'id' => $variantId ? (string) $variantId : 'new-'.$sku, 'sku' => $sku, 'title' => $title, 'options' => [],
                'price' => 199, 'compareAtPrice' => null, 'costPrice' => null, 'inventoryQuantity' => 10,
                'weightGrams' => null, 'barcode' => null, 'image' => null, 'enabled' => true,
            ]],
        ];
    }
}
