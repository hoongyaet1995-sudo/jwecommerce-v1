<?php

namespace Tests\Feature\Admin;

use App\Models\CmsMediaAsset;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edited_description_reaches_pdp_and_plain_text_listing(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $category=\App\Models\Category::create(['name'=>'Copy','slug'=>'copy','status'=>'active']);
        $payload=$this->productPayload((string)$category->id, []);
        $payload['descriptionHtml']='<p>Edited <strong>cotton</strong> &amp; comfort</p><ul><li>Wash gently</li></ul>';
        $payload['summary']='';
        $payload['status']='active';
        $created=$this->postJson('/admin/api/catalog/products',$payload)->assertCreated()->json('data');
        $this->get('/products/'.$created['slug'])->assertOk()->assertSee('<strong>cotton</strong>',false)->assertSee('<li>Wash gently</li>',false);
        $this->get('/shop')->assertOk()->assertSee('Edited cotton &amp; comfort',false)->assertDontSee('&lt;strong&gt;',false);
    }

    public function test_stale_product_form_cannot_restore_stock_sold_since_it_was_loaded(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $category=\App\Models\Category::create(['name'=>'Test','slug'=>'test','status'=>'active']);
        $payload=$this->productPayload((string)$category->id,[]);
        $created=$this->postJson('/admin/api/catalog/products',$payload)->assertCreated()->json('data');
        $payload['variants'][0]['id']=$created['variants'][0]['id'];
        $payload['variants'][0]['stockVersion']=0;
        app(\App\Services\Inventory\InventoryMutationService::class)->change((int)$created['variants'][0]['id'],-2,'test:sale','Sale');
        $this->patchJson('/admin/api/catalog/products/'.$created['id'],$payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.inventoryQuantity');
        $payload['variants'][0]['stockVersion']=1;
        $payload['variants'][0]['inventoryQuantity']=11;
        $this->patchJson('/admin/api/catalog/products/'.$created['id'],$payload)->assertOk()->assertJsonPath('data.variants.0.stockVersion',2);
        $this->assertDatabaseHas('product_variants',['id'=>$created['variants'][0]['id'],'stock'=>11]);
        $this->assertDatabaseCount('inventory_movements',2);
        $payload['variants'][0]['id']='new-replacement';
        $payload['variants'][0]['sku']='REPLACEMENT-SKU';
        unset($payload['variants'][0]['stockVersion']);
        $this->patchJson('/admin/api/catalog/products/'.$created['id'],$payload)->assertUnprocessable()->assertJsonValidationErrors('variants');
        $this->assertDatabaseHas('product_variants',['id'=>$created['variants'][0]['id'],'stock'=>11]);
        $this->assertDatabaseMissing('product_variants',['sku'=>'REPLACEMENT-SKU']);
    }

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

    public function test_catalog_ignores_legacy_product_tax_class_input(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxClass = TaxClass::factory()->create(['name' => 'Standard SST Goods']);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Taxable catalog', 'slug' => 'taxable-catalog', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $payload = $this->productPayload((string) $category['id'], []);
        $payload['taxClassId'] = (string) $taxClass->id;

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('products', ['id' => $product['id'], 'tax_class_id' => null]);
        $this->actingAs($admin)->getJson('/admin/api/catalog')
            ->assertOk()
            ->assertJsonMissing(['taxClassId' => (string) $taxClass->id]);
    }

    public function test_catalog_round_trips_variant_package_dimensions_and_rejects_non_positive_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Package catalog', 'slug' => 'package-catalog', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $payload = $this->productPayload((string) $category['id'], []);
        $payload['variants'][0]['packageLengthCm'] = 30.5;
        $payload['variants'][0]['packageWidthCm'] = 12;
        $payload['variants'][0]['packageHeightCm'] = 8;

        $product = $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.variants.0.packageLengthCm', 30.5)
            ->assertJsonPath('data.variants.0.packageWidthCm', 12)
            ->assertJsonPath('data.variants.0.packageHeightCm', 8)
            ->json('data');

        $this->assertDatabaseHas('product_variants', [
            'id' => $product['variants'][0]['id'],
            'package_length_cm' => 30.5,
            'package_width_cm' => 12,
            'package_height_cm' => 8,
        ]);

        $payload['variants'][0]['id'] = $product['variants'][0]['id'];
        $payload['variants'][0]['packageLengthCm'] = 0;

        $this->actingAs($admin)->patchJson('/admin/api/catalog/products/'.$product['id'], $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.packageLengthCm');
    }

    public function test_catalog_allows_legacy_variants_without_package_dimensions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Legacy catalog', 'slug' => 'legacy-catalog', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson('/admin/api/catalog/products', $this->productPayload((string) $category['id'], []))
            ->assertCreated()
            ->assertJsonPath('data.variants.0.packageLengthCm', null)
            ->assertJsonPath('data.variants.0.packageWidthCm', null)
            ->assertJsonPath('data.variants.0.packageHeightCm', null);
    }

    public function test_catalog_rejects_package_dimensions_that_exceed_database_precision(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->actingAs($admin)->postJson('/admin/api/catalog/categories', [
            'name' => 'Precision catalog', 'slug' => 'precision-catalog', 'status' => 'active', 'sortOrder' => 1,
        ])->assertCreated()->json('data');
        $payload = $this->productPayload((string) $category['id'], []);
        $payload['variants'][0]['packageLengthCm'] = 0.001;
        $payload['variants'][0]['packageWidthCm'] = 12.345;
        $payload['variants'][0]['packageHeightCm'] = 8.999;

        $this->actingAs($admin)->postJson('/admin/api/catalog/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.packageLengthCm',
                'variants.0.packageWidthCm',
                'variants.0.packageHeightCm',
            ]);
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
        $payload['variants'][0]['stockVersion'] = $product['variants'][0]['stockVersion'];
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
