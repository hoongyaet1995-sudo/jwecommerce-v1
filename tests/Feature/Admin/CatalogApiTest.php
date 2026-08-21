<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_catalog_changes_persist_and_active_product_is_visible_on_storefront(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Studio Audio', 'slug' => 'studio-audio', 'parentId' => null,
            'icon' => 'Headphones', 'description' => 'Reference listening systems.',
            'seoTitle' => 'Studio Audio', 'seoDescription' => 'Reference systems.',
            'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $collection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', [
            'title' => 'Reference Collection', 'slug' => 'reference-collection',
            'description' => 'Curated reference equipment.', 'coverImage' => '/images/catalog/aether-1.jpg',
            'type' => 'manual', 'manualProductIds' => [], 'status' => 'active',
        ])->assertCreated()->json('data');

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->productPayload(
            (string) $category['id'],
            [(string) $collection['id']],
        ))->assertCreated()
            ->assertJsonPath('data.variants.0.sku', 'REF-001')
            ->assertJsonPath('data.basePrice', 299)
            ->json('data');

        $this->assertDatabaseHas('products', ['id' => $product['id'], 'base_price' => 29900, 'status' => 'active']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'REF-001', 'stock' => 12, 'price' => 29900]);
        $this->assertDatabaseHas('collection_product', ['collection_id' => $collection['id'], 'product_id' => $product['id']]);

        $this->actingAs($admin)->getJson('/admin/api/catalog')
            ->assertOk()
            ->assertJsonPath('products.0.title', 'Reference Monitor Headphones');

        $this->get('/shop')->assertOk()->assertSee('Reference Monitor Headphones');
    }

    public function test_duplicate_sku_rejects_the_whole_product_transaction(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Audio', 'slug' => 'audio', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $payload = $this->productPayload((string) $category['id'], []);
        $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)->assertCreated();
        $payload['slug'] = 'second-product';
        $payload['title'] = 'Second Product';

        $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.sku');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_dynamic_collection_rules_materialize_products_for_public_collection_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Audio', 'slug' => 'audio', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $collection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', [
            'title' => 'JW Acoustic Products', 'slug' => 'jw-acoustic-products',
            'type' => 'dynamic', 'matchType' => 'all', 'status' => 'active',
            'dynamicRules' => [[
                'id' => 'rule-brand', 'field' => 'vendor', 'operator' => 'equals', 'value' => 'JW Acoustic',
            ]],
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->productPayload((string) $category['id'], []))->assertCreated();

        $this->assertDatabaseHas('collection_product', ['collection_id' => $collection['id']]);
        $this->get('/collections/jw-acoustic-products')->assertOk()->assertSee('Reference Monitor Headphones');
    }

    public function test_catalog_write_requires_admin_authentication(): void
    {
        $this->postJson('/admin/api/catalog/categories', [
            'name' => 'Audio', 'slug' => 'audio', 'status' => 'active', 'sortOrder' => 1,
        ])->assertUnauthorized();
    }

    /** @return array<string, mixed> */
    private function productPayload(string $categoryId, array $collectionIds): array
    {
        return [
            'title' => 'Reference Monitor Headphones', 'slug' => 'reference-monitor-headphones',
            'brand' => 'JW Acoustic', 'categoryId' => $categoryId, 'collectionIds' => $collectionIds,
            'summary' => 'Closed-back reference headphones.', 'descriptionHtml' => '<p>Reference monitoring.</p>',
            'images' => ['/images/catalog/aether-1.jpg'], 'status' => 'active', 'hasVariants' => true,
            'basePrice' => 299, 'baseCompareAtPrice' => 349, 'featured' => true,
            'tags' => ['audio', 'reference'], 'options' => [['id' => 'color', 'name' => 'Color', 'values' => ['Black']]],
            'variants' => [[
                'id' => 'new-variant', 'sku' => 'REF-001', 'title' => 'Black',
                'options' => ['Color' => 'Black'], 'price' => 299, 'compareAtPrice' => 349,
                'costPrice' => 120, 'inventoryQuantity' => 12, 'weightGrams' => 320,
                'barcode' => 'REF001', 'image' => '/images/catalog/aether-1.jpg', 'enabled' => true,
            ]],
            'seoTitle' => 'Reference Monitor Headphones', 'seoDescription' => 'Studio monitoring.',
        ];
    }
}
