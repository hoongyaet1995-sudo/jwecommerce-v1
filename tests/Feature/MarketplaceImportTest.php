<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Marketplace\MarketplaceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.operations_enabled'=>true]);
    }

    public function test_import_endpoint_requires_admin_and_uses_server_owned_catalog_and_actor(): void
    {
        $this->catalog();
        $listing = DB::table('marketplace_listing_variants')->value('id');
        $url = '/admin/api/marketplace/listings/'.$listing.'/import-product';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>false]))->postJson($url)->assertUnauthorized();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $this->postJson($url, ['stock'=>500, 'status'=>'active', 'actorId'=>999])->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $product = $this->postJson($url)->assertOk()->assertJsonPath('status', 'draft')->json('productId');
        $this->postJson($url)->assertOk()->assertJsonPath('productId', $product);
        $this->assertDatabaseHas('marketplace_product_imports', ['product_id'=>$product, 'actor_id'=>42]);
        $this->assertDatabaseCount('products', 1);
        $this->postJson('/admin/api/marketplace/listings/999999/import-product')->assertNotFound();
    }

    public function test_import_creates_draft_zero_stock_and_retries_without_duplicate_even_after_unlink(): void
    {
        $store = $this->catalog();
        $service = app(MarketplaceImportService::class);
        $productId = $service->importProduct($store, '100', 42);
        $product = Product::findOrFail($productId);
        $this->assertSame('draft', $product->status);
        $this->assertSame('<p>Source &amp; details</p>', $product->description);
        $this->assertSame('Source Brand', $product->brand);
        $this->assertNull($product->category_id);
        $this->get('/products/'.$product->handle)->assertNotFound();
        $this->assertEquals(1234, $product->base_price);
        $this->assertEquals(0, $product->variants()->sum('stock'));
        $this->assertDatabaseCount('marketplace_variant_mappings', 2);
        DB::table('marketplace_variant_mappings')->delete();
        $this->assertSame($productId, $service->importProduct($store, '100', 42));
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseCount('marketplace_mapping_events', 2);
    }

    public function test_existing_sku_blocks_whole_product_without_partial_import(): void
    {
        $store = $this->catalog();
        $product = Product::create(['title'=>'Existing', 'handle'=>'existing', 'base_price'=>100, 'status'=>'active']);
        $product->variants()->create(['sku'=>'SOURCE-2', 'title'=>'Existing', 'price'=>100, 'stock'=>9]);
        try {
            app(MarketplaceImportService::class)->importProduct($store, '100', 42);
            $this->fail('Existing SKU was duplicated.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);
    }

    public function test_invalid_later_variant_rolls_back_all_import_records(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $rows = DB::table('marketplace_listing_variants')->orderBy('id')->get();
        $original = json_decode($rows[1]->observed_data, true);
        foreach ([['currency'=>'USD'], ['sku'=>''], ['price'=>null], ['price'=>'12.345'], ['title'=>''], ['descriptionType'=>'unsupported'], ['brand'=>['bad']], ['brand'=>str_repeat('x', 256)]] as $invalid) {
            DB::table('marketplace_listing_variants')->where('id', $rows[1]->id)
                ->update(['observed_data'=>json_encode(array_replace($original, $invalid))]);
            $this->postJson('/admin/api/marketplace/listings/'.$rows[0]->id.'/import-product')->assertUnprocessable();
            foreach (['products', 'product_variants', 'marketplace_product_imports', 'marketplace_variant_mappings', 'marketplace_mapping_events'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }
        DB::table('marketplace_listing_variants')->where('id', $rows[1]->id)->update(['observed_data'=>json_encode($original)]);
        $this->postJson('/admin/api/marketplace/listings/'.$rows[0]->id.'/import-product')->assertOk();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_import_preserves_safe_channel_main_image_without_fetching_or_duplicating_it(): void
    {
        $store = $this->catalog();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        foreach (DB::table('marketplace_listing_variants')->get() as $row) {
            $data = json_decode($row->observed_data, true);
            $data['productImage'] = 'https://down-my.img.susercontent.com/file/local-fixture';
            DB::table('marketplace_listing_variants')->where('id', $row->id)->update(['observed_data'=>json_encode($data)]);
        }
        $service = app(MarketplaceImportService::class);
        $id = $service->importProduct($store, '100', 42);
        $this->assertDatabaseHas('product_images', ['product_id'=>$id, 'path'=>'https://down-my.img.susercontent.com/file/local-fixture',
            'alt'=>'Imported Product', 'position'=>0]);
        $this->assertSame($id, $service->importProduct($store, '100', 42));
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseHas('products', ['id'=>$id, 'status'=>'draft']);
        $this->assertEquals(0, DB::table('product_variants')->sum('stock'));
    }

    public function test_import_rejects_unsafe_channel_image_before_creating_product(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $row = DB::table('marketplace_listing_variants')->orderBy('id')->first();
        $original = json_decode($row->observed_data, true);
        foreach (['javascript:alert(1)', 'https://127.0.0.1/private', 'http://cdn.shop.com/image', ['malformed']] as $image) {
            DB::table('marketplace_listing_variants')->where('id', $row->id)
                ->update(['observed_data'=>json_encode(array_replace($original, ['productImage'=>$image]))]);
            $this->postJson('/admin/api/marketplace/listings/'.$row->id.'/import-product')->assertUnprocessable();
            foreach (['products', 'product_images', 'product_variants', 'marketplace_product_imports', 'marketplace_variant_mappings'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }
    }

    public function test_import_preserves_gallery_order_and_deduplicates_repeated_variant_observations(): void
    {
        $store = $this->catalog();
        foreach (DB::table('marketplace_listing_variants')->get() as $row) {
            $data = json_decode($row->observed_data, true);
            $data['productImages'] = ['https://down-my.img.susercontent.com/file/front', 'https://down-my.img.susercontent.com/file/back', 'https://down-my.img.susercontent.com/file/front'];
            DB::table('marketplace_listing_variants')->where('id', $row->id)->update(['observed_data'=>json_encode($data)]);
        }
        $id = app(MarketplaceImportService::class)->importProduct($store, '100', 42);
        $this->assertSame(['https://down-my.img.susercontent.com/file/front', 'https://down-my.img.susercontent.com/file/back'],
            DB::table('product_images')->where('product_id', $id)->orderBy('position')->pluck('path')->all());
    }

    public function test_import_rejects_invalid_gallery_without_partial_images_or_product(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $row = DB::table('marketplace_listing_variants')->orderBy('id')->first();
        $data = json_decode($row->observed_data, true);
        foreach (['not-a-list', ['https://down-my.img.susercontent.com/file/front', 'https://127.0.0.1/private']] as $gallery) {
            $data['productImages'] = $gallery;
            DB::table('marketplace_listing_variants')->where('id', $row->id)->update(['observed_data'=>json_encode($data)]);
            $this->postJson('/admin/api/marketplace/listings/'.$row->id.'/import-product')->assertUnprocessable();
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('product_images', 0);
        }
    }

    private function catalog(): int
    {
        \App\Models\User::factory()->create(['id'=>42, 'is_admin'=>true]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Source',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        foreach ([1, 2] as $variant) {
            DB::table('marketplace_listing_variants')->insert(['store_id'=>$store, 'external_product_id'=>'100',
                'external_variant_id'=>(string) $variant, 'observed_data'=>json_encode(['productTitle'=>'Imported Product',
                    'descriptionType'=>'normal', 'description'=>'Source & details',
                    'brand'=>'Source Brand', 'externalCategoryId'=>555,
                    'title'=>'Variant '.$variant, 'sku'=>'SOURCE-'.$variant, 'price'=>'12.34', 'currency'=>'MYR', 'stock'=>500]),
                'fetched_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
        }
        return $store;
    }
}
