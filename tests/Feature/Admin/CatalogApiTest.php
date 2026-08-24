<?php

namespace Tests\Feature\Admin;

use App\Models\CmsMediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_product_media_objects_preserve_alt_text_and_primary_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Media audio', 'slug' => 'media-audio', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $payload = $this->productPayload((string) $category['id'], []);
        $payload['images'] = [
            ['path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000', 'alt' => 'Front hero'],
            ['path' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000', 'alt' => 'Side profile'],
        ];

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.images.0.alt', 'Front hero')
            ->assertJsonPath('data.images.0.position', 0)
            ->assertJsonPath('data.images.1.alt', 'Side profile')
            ->assertJsonPath('data.images.1.position', 1)
            ->json('data');

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product['id'],
            'path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000',
            'alt' => 'Front hero',
            'position' => 0,
        ]);
    }

    public function test_variant_visuals_round_trip_as_mutually_exclusive_color_or_image_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Visual audio', 'slug' => 'visual-audio', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $payload = $this->productPayload((string) $category['id'], []);
        $payload['options'] = [[
            'id' => 'color', 'name' => 'Color', 'values' => ['Midnight Black', 'Studio White'],
            'displayType' => 'color_palette',
            'valueVisuals' => [
                'Midnight Black' => ['visualType' => 'color', 'colorCode' => '#111827'],
                'Studio White' => ['visualType' => 'image', 'optionImage' => '/images/catalog/aether-1.jpg'],
            ],
        ]];
        $payload['variants'][0]['visualType'] = 'image';
        $payload['variants'][0]['colorCode'] = null;
        $payload['variants'][0]['optionImage'] = '/images/catalog/aether-1.jpg';

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.options.0.displayType', 'color_palette')
            ->assertJsonPath('data.options.0.valueVisuals.Midnight Black.colorCode', '#111827')
            ->assertJsonPath('data.variants.0.visualType', 'image')
            ->assertJsonPath('data.variants.0.optionImage', '/images/catalog/aether-1.jpg')
            ->json('data');

        $this->assertDatabaseHas('product_variants', [
            'id' => $product['variants'][0]['id'],
            'visual_type' => 'image',
            'color_code' => null,
            'option_image' => '/images/catalog/aether-1.jpg',
        ]);
    }

    public function test_category_and_collection_managed_media_are_validated_and_referenced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $asset = $this->managedAsset();

        $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Unsafe media', 'slug' => 'unsafe-media', 'image' => 'data:image/png;base64,AAAA',
            'status' => 'active', 'sortOrder' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Managed media', 'slug' => 'managed-media', 'image' => $asset->url,
            'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $collection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', [
            'title' => 'Managed cover', 'slug' => 'managed-cover', 'coverImage' => $asset->url,
            'type' => 'manual', 'manualProductIds' => [], 'status' => 'active',
        ])->assertCreated()->json('data');

        $this->assertDatabaseCount('catalog_media_references', 2);

        $this->actingAs($admin)->deleteJson('/admin/api/catalog/categories/'.$category['id'])->assertNoContent();
        $this->assertDatabaseCount('catalog_media_references', 1);
        $this->actingAs($admin)->deleteJson('/admin/api/catalog/collections/'.$collection['id'])->assertNoContent();
        $this->assertDatabaseCount('catalog_media_references', 0);
        $this->assertNotNull($asset->fresh()->orphaned_at);
    }

    public function test_catalog_update_round_trip_preserves_category_collection_and_primary_product_media(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Parent', 'slug' => 'parent', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Child', 'slug' => 'child', 'parentId' => $parent['id'], 'icon' => 'Headphones',
            'description' => 'Original', 'seoTitle' => 'Child SEO', 'seoDescription' => 'Child Meta',
            'seoKeywords' => 'audio,studio', 'status' => 'draft', 'sortOrder' => 9,
        ])->assertCreated()->json('data');
        $collection = $this->actingAs($admin)->postJson('/admin/api/catalog/collections', [
            'title' => 'Manual', 'slug' => 'manual', 'type' => 'manual', 'manualProductIds' => [], 'status' => 'active',
        ])->assertCreated()->json('data');
        $payload = $this->productPayload((string) $category['id'], [(string) $collection['id']]);
        $payload['images'] = [
            ['path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000', 'alt' => 'First'],
            ['path' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000', 'alt' => 'Second'],
        ];
        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)->assertCreated()->json('data');

        $payload['images'] = array_reverse($payload['images']);
        $payload['variants'][0]['id'] = $product['variants'][0]['id'];
        $payload['variants'][0]['inventoryQuantity'] = 7;
        $this->actingAs($admin)->patchJson('/admin/api/catalog/products/'.$product['id'], $payload)
            ->assertOk()
            ->assertJsonPath('data.images.0.path', $payload['images'][0]['path'])
            ->assertJsonPath('data.images.0.alt', 'Second')
            ->assertJsonPath('data.totalInventory', 7);

        $this->actingAs($admin)->getJson('/admin/api/catalog')
            ->assertOk()
            ->assertJsonPath('categories.1.parentId', (string) $parent['id'])
            ->assertJsonPath('categories.1.status', 'draft')
            ->assertJsonPath('products.0.images.0.alt', 'Second');
        $this->get('/shop')->assertOk()->assertSee('Reference Monitor Headphones');
    }

    public function test_archiving_a_product_removes_it_from_public_catalog_consumers_without_deleting_its_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Archive category', 'slug' => 'archive-category', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->productPayload((string) $category['id'], []))
            ->assertCreated()->json('data');

        $this->actingAs($admin)->deleteJson('/admin/api/catalog/products/'.$product['id'])->assertNoContent();

        $this->assertDatabaseHas('products', ['id' => $product['id'], 'status' => 'archived']);
        $this->get('/shop')->assertOk()->assertDontSee('Reference Monitor Headphones');
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

    private function managedAsset(): CmsMediaAsset
    {
        $id = (string) Str::uuid();
        $path = "cms/media/{$id}.jpg";

        return CmsMediaAsset::query()->create([
            'id' => $id, 'disk' => 'public', 'path' => $path, 'url' => '/storage/'.$path,
            'original_name' => 'catalog.jpg', 'mime_type' => 'image/jpeg', 'size' => 256,
            'width' => 1, 'height' => 1, 'checksum' => hash('sha256', $id), 'orphaned_at' => now(),
        ]);
    }
}
