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

    public function test_reviewed_transfer_moves_only_after_confirmation_and_preserves_identity():void
    {
        $this->catalog();$listing=DB::table('marketplace_listing_variants')->value('id');
        $id=app(MarketplaceImportService::class)->importVariantAsProduct($listing,42);
        $variant=\App\Models\ProductVariant::findOrFail($id);$old=$variant->product_id;
        app(\App\Services\Marketplace\MarketplaceFoundationService::class)->map($listing,null,null,42);
        $to=Product::create(['title'=>'Destination','handle'=>'destination','base_price'=>100,'status'=>'draft']);
        $service=app(\App\Services\Marketplace\VariantOwnershipTransfer::class);
        $review=$service->run($listing,$to->id,42);
        $this->assertSame($old,$variant->fresh()->product_id);
        $variant->update(['stock'=>1]);
        try {$service->run($listing,$to->id,42,$review['token'],'Correct parent selection');$this->fail('Stock must block a previously reviewed move');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $this->assertSame($old,$variant->fresh()->product_id);
        $variant->update(['stock'=>0]);$review=$service->run($listing,$to->id,42);
        $service->run($listing,$to->id,42,$review['token'],'Correct parent selection');
        $this->assertSame($to->id,$variant->fresh()->product_id);
        $this->assertDatabaseHas('marketplace_variant_mappings',['listing_variant_id'=>$listing,'product_variant_id'=>$id]);
        $this->assertDatabaseHas('marketplace_variant_transfers',['product_variant_id'=>$id,'from_product_id'=>$old,'to_product_id'=>$to->id,'actor_id'=>42]);
        $this->assertDatabaseCount('product_variants',1);
    }

    public function test_append_reuses_sku_in_selected_parent_without_overwriting_stock(): void
    {
        $this->catalog();
        $listing=DB::table('marketplace_listing_variants')->first();
        $sku=json_decode($listing->observed_data,true)['sku'];
        $parent=Product::create(['title'=>'Selected Parent','handle'=>'selected-parent','base_price'=>100,'status'=>'draft']);
        $variant=$parent->variants()->create(['sku'=>$sku,'title'=>'Existing','price'=>500,'stock'=>7]);
        $id=app(MarketplaceImportService::class)->importVariant($listing->id,$parent->id,42);
        $this->assertSame($variant->id,$id);
        $this->assertDatabaseHas('marketplace_variant_mappings',['listing_variant_id'=>$listing->id,'product_variant_id'=>$id]);
        $this->assertDatabaseHas('product_variants',['id'=>$id,'stock'=>7,'price'=>500]);
        $this->assertDatabaseCount('product_variants',1);
    }

    public function test_new_master_then_append_creates_two_variants_under_selected_parent(): void
    {
        $this->catalog();
        $ids=DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $service=app(MarketplaceImportService::class);
        $first=$service->importVariantAsProduct($ids[0],42);
        $parent=\App\Models\ProductVariant::findOrFail($first)->product_id;
        $second=$service->importVariant($ids[1],$parent,42);
        $this->assertDatabaseCount('products',1);
        $this->assertDatabaseHas('product_variants',['id'=>$second,'product_id'=>$parent,'stock'=>0]);
        $this->assertSame($second,$service->importVariant($ids[1],$parent,42));
        $this->assertDatabaseCount('product_variants',2);
    }

    public function test_single_variant_import_preserves_image_and_shipping_measurements(): void
    {
        $this->catalog();
        $listing=DB::table('marketplace_listing_variants')->first();
        $data=json_decode($listing->observed_data,true);
        $data['variantImage']='https://cf.shopee.com.my/file/black';
        $data['weightGrams']=1000;
        $data['packageLengthCm']=1;$data['packageWidthCm']=2;$data['packageHeightCm']=3;
        DB::table('marketplace_listing_variants')->where('id',$listing->id)->update(['observed_data'=>json_encode($data)]);
        $id=app(MarketplaceImportService::class)->importVariantAsProduct($listing->id,42);
        $this->assertDatabaseHas('product_variants',['id'=>$id,'image_url'=>$data['variantImage'],'option_image'=>$data['variantImage'],'visual_type'=>'image','weight_grams'=>1000,'package_length_cm'=>1,'package_width_cm'=>2,'package_height_cm'=>3,'stock'=>0]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.operations_enabled'=>true]);
    }

    public function test_imported_variants_inherit_explicit_test_store_provenance():void
    {
        $store=$this->catalog();
        config(['marketplace.basic_wms_enabled'=>true]);
        app(\App\Services\Inventory\TestDataClassificationService::class)->markTest('store',$store,\App\Models\User::findOrFail(42),'Sandbox import');
        $product=app(MarketplaceImportService::class)->importProduct($store,'100',42);
        $this->assertSame(2,DB::table('product_variants')->where('product_id',$product)->where('data_classification','test')->where('classified_by',42)->count());
        $this->assertSame(0,(int)DB::table('product_variants')->where('product_id',$product)->sum('stock'));
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

    public function test_import_can_be_enabled_without_enabling_provider_operations(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $listing = DB::table('marketplace_listing_variants')->value('id');
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>false,
            'marketplace.import_operations_enabled'=>false]);
        $url = '/admin/api/marketplace/listings/'.$listing.'/import-product';
        $this->postJson($url)->assertStatus(503);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('localCapabilities.importProducts', false);
        config(['marketplace.import_operations_enabled'=>true]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('localCapabilities.importProducts', true);
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'draft');
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(0, (int) DB::table('product_variants')->sum('stock'));
        $this->postJson('/admin/api/marketplace/stores/1/catalog-fetch')->assertStatus(503);
        $this->postJson('/admin/api/marketplace/stores/1/orders-fetch', ['from'=>1,'to'=>2])->assertStatus(503);
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

    public function test_single_variant_import_preserves_destination_and_siblings_and_retry_identity(): void
    {
        $this->catalog();
        $destination = Product::create(['title'=>'Existing parent', 'handle'=>'parent', 'base_price'=>900, 'status'=>'draft']);
        $destination->variants()->create(['sku'=>'EXISTING', 'title'=>'Original', 'price'=>900, 'stock'=>7]);
        $listing = DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $service = app(MarketplaceImportService::class);
        $variantId = $service->importVariant($listing, $destination->id, 42);
        $this->assertDatabaseHas('product_variants', ['id'=>$variantId, 'product_id'=>$destination->id, 'sku'=>'SOURCE-1', 'price'=>1234, 'stock'=>0]);
        $this->assertDatabaseMissing('product_variants', ['sku'=>'SOURCE-2']);
        $this->assertDatabaseHas('products', ['id'=>$destination->id, 'title'=>'Existing parent', 'base_price'=>900, 'status'=>'draft']);
        $this->assertDatabaseCount('marketplace_variant_mappings', 1);
        DB::table('marketplace_variant_mappings')->delete();
        $this->assertSame($variantId, $service->importVariant($listing, $destination->id, 42));
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);
        $this->assertEquals(7, DB::table('product_variants')->sum('stock'));
    }

    public function test_single_variant_endpoint_checks_permissions_input_and_import_gate(): void
    {
        $this->catalog();
        $parent = Product::create(['title'=>'Target', 'handle'=>'target', 'base_price'=>900, 'status'=>'draft']);
        $listing = DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $url = '/admin/api/marketplace/listings/'.$listing.'/import-variant';
        $this->postJson($url, ['productId'=>$parent->id])->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>false]))
            ->postJson($url, ['productId'=>$parent->id])->assertUnauthorized();
        $this->actingAs(\App\Models\User::findOrFail(42));
        config(['marketplace.operations_enabled'=>false, 'marketplace.import_operations_enabled'=>false]);
        $this->postJson($url, ['productId'=>$parent->id])->assertStatus(503);
        config(['marketplace.import_operations_enabled'=>true]);
        $this->postJson($url)->assertUnprocessable();
        $this->postJson($url, ['productId'=>$parent->id, 'stock'=>10])->assertUnprocessable();
        $this->postJson($url, ['productId'=>99999])->assertUnprocessable();
        $variant = $this->postJson($url, ['productId'=>$parent->id])->assertOk()->json('variantId');
        $this->assertIsInt($variant);
        $this->assertDatabaseHas('marketplace_variant_imports', ['product_variant_id'=>$variant, 'actor_id'=>42]);
        $this->assertDatabaseHas('product_variants', ['id'=>$variant, 'stock'=>0]);
        $this->postJson($url, ['productId'=>$parent->id])->assertOk()->assertJsonPath('variantId', $variant);
    }

    public function test_single_variant_rejects_duplicate_sku_and_changed_retry_destination_without_side_effects(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $first = Product::create(['title'=>'First', 'handle'=>'first', 'base_price'=>100, 'status'=>'draft']);
        $second = Product::create(['title'=>'Second', 'handle'=>'second', 'base_price'=>200, 'status'=>'draft']);
        $listings = DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $url = '/admin/api/marketplace/listings/'.$listings[0].'/import-variant';
        $variantId = $this->postJson($url, ['productId'=>$first->id])->assertOk()->json('variantId');
        $this->postJson($url, ['productId'=>$second->id])->assertConflict();
        $other = DB::table('marketplace_listing_variants')->find($listings[1]);
        $data = json_decode($other->observed_data, true);
        $data['sku'] = 'SOURCE-1';
        DB::table('marketplace_listing_variants')->where('id', $other->id)->update(['observed_data'=>json_encode($data)]);
        $this->postJson('/admin/api/marketplace/listings/'.$other->id.'/import-variant', ['productId'=>$second->id])->assertConflict();
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('marketplace_variant_imports', 1);
        $this->assertDatabaseCount('marketplace_mapping_events', 1);
        $this->assertDatabaseHas('product_variants', ['id'=>$variantId, 'product_id'=>$first->id, 'stock'=>0]);
    }

    public function test_single_variant_invalid_observation_does_not_change_destination(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $parent = Product::create(['title'=>'Parent', 'handle'=>'parent-invalid', 'base_price'=>100, 'status'=>'active']);
        $source = DB::table('marketplace_listing_variants')->orderBy('id')->first();
        $original = json_decode($source->observed_data, true);
        foreach ([['sku'=>[]], ['title'=>''], ['currency'=>'USD'], ['price'=>'1.234'], ['price'=>null]] as $invalid) {
            DB::table('marketplace_listing_variants')->where('id', $source->id)->update(['observed_data'=>json_encode(array_replace($original, $invalid))]);
            $this->postJson('/admin/api/marketplace/listings/'.$source->id.'/import-variant', ['productId'=>$parent->id])->assertUnprocessable();
            $this->assertDatabaseCount('product_variants', 0);
            $this->assertDatabaseCount('marketplace_variant_imports', 0);
            $this->assertDatabaseCount('marketplace_variant_mappings', 0);
        }
        $this->assertDatabaseHas('products', ['id'=>$parent->id, 'title'=>'Parent', 'status'=>'active', 'base_price'=>100]);
    }

    public function test_whole_product_import_does_not_duplicate_a_previously_imported_unlinked_renamed_variant(): void
    {
        $store = $this->catalog();
        $parent = Product::create(['title'=>'Existing', 'handle'=>'retained-parent', 'base_price'=>100, 'status'=>'draft']);
        $listing = DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $service = app(MarketplaceImportService::class);
        $variant = $service->importVariant($listing, $parent->id, 42);
        DB::table('marketplace_variant_mappings')->delete();
        DB::table('product_variants')->where('id', $variant)->update(['sku'=>'RENAMED-BY-EMPLOYEE']);
        $this->actingAs(\App\Models\User::findOrFail(42))
            ->postJson('/admin/api/marketplace/listings/'.$listing.'/import-product')->assertConflict();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);
    }

    public function test_single_variant_can_create_a_new_draft_without_importing_siblings(): void
    {
        $this->catalog();
        $listing = DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $service = app(MarketplaceImportService::class);
        $id = $service->importVariantAsProduct($listing, 42);
        $variant = \App\Models\ProductVariant::findOrFail($id);
        $this->assertSame('draft', $variant->product->status);
        $this->assertSame('Imported Product', $variant->product->title);
        $this->assertEquals(1234, $variant->product->base_price);
        $this->assertSame('<p>Source &amp; details</p>', $variant->product->description);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseMissing('product_variants', ['sku'=>'SOURCE-2']);
        $this->assertSame($id, $service->importVariantAsProduct($listing, 42));
        $this->assertDatabaseCount('products', 1);
        $this->get('/products/'.$variant->product->handle)->assertNotFound();
    }

    public function test_new_draft_variant_endpoint_requires_explicit_destination_and_rolls_back_invalid_source(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        config(['marketplace.import_operations_enabled'=>true]);
        $source = DB::table('marketplace_listing_variants')->orderBy('id')->first();
        $url = '/admin/api/marketplace/listings/'.$source->id.'/import-variant';
        $this->postJson($url, ['newProduct'=>true, 'productId'=>1])->assertUnprocessable();
        $this->postJson($url, ['newProduct'=>false])->assertUnprocessable();
        $data = json_decode($source->observed_data, true);
        DB::table('marketplace_listing_variants')->where('id', $source->id)
            ->update(['observed_data'=>json_encode(array_replace($data, ['price'=>'invalid']))]);
        $this->postJson($url, ['newProduct'=>true])->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('marketplace_variant_imports', 0);
        DB::table('marketplace_listing_variants')->where('id', $source->id)->update(['observed_data'=>json_encode($data)]);
        $response = $this->postJson($url, ['newProduct'=>true])->assertOk();
        $id = $response->json('variantId');
        $this->assertDatabaseHas('product_variants', ['id'=>$id, 'product_id'=>$response->json('productId'), 'stock'=>0]);
        $this->assertDatabaseHas('products', ['id'=>$response->json('productId'), 'status'=>'draft']);
        $this->postJson($url, ['newProduct'=>true])->assertOk()->assertJsonPath('variantId', $id);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_reviewed_sku_is_used_without_changing_channel_snapshot_and_retry_does_not_rename(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        config(['marketplace.import_operations_enabled'=>true]);
        $source = DB::table('marketplace_listing_variants')->orderBy('id')->first();
        $url = '/admin/api/marketplace/listings/'.$source->id.'/import-variant';
        foreach (['', ' ', null, [], str_repeat('a', 256)] as $invalid) {
            $this->postJson($url, ['newProduct'=>true, 'sku'=>$invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
        $response = $this->postJson($url, ['newProduct'=>true, 'sku'=>' JW-REVIEWED-1 '])->assertOk();
        $id = $response->json('variantId');
        $this->assertDatabaseHas('product_variants', ['id'=>$id, 'sku'=>'JW-REVIEWED-1', 'stock'=>0]);
        $this->assertSame($source->observed_data, DB::table('marketplace_listing_variants')->where('id', $source->id)->value('observed_data'));
        $this->postJson($url, ['newProduct'=>true, 'sku'=>'SECOND-CODE'])->assertOk()->assertJsonPath('variantId', $id);
        $this->assertDatabaseHas('product_variants', ['id'=>$id, 'sku'=>'JW-REVIEWED-1']);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_reviewed_sku_conflict_rolls_back_new_draft_and_can_be_resolved_by_appending(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        config(['marketplace.import_operations_enabled'=>true]);
        $parent = Product::create(['title'=>'Keep parent', 'handle'=>'keep-parent', 'base_price'=>900, 'status'=>'draft']);
        $existing = $parent->variants()->create(['sku'=>'OCCUPIED', 'title'=>'Original', 'price'=>900, 'stock'=>7]);
        $listing = DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $url = '/admin/api/marketplace/listings/'.$listing.'/import-variant';
        $this->postJson($url, ['newProduct'=>true, 'sku'=>'OCCUPIED'])->assertConflict();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('marketplace_variant_imports', 0);
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);
        $this->assertDatabaseCount('marketplace_mapping_events', 0);
        $response = $this->postJson($url, ['productId'=>$parent->id, 'sku'=>'REVIEWED-APPEND'])->assertOk();
        $this->assertDatabaseHas('product_variants', ['id'=>$response->json('variantId'),
            'product_id'=>$parent->id, 'sku'=>'REVIEWED-APPEND', 'stock'=>0, 'price'=>1234]);
        $this->assertDatabaseHas('product_variants', ['id'=>$existing->id, 'sku'=>'OCCUPIED', 'stock'=>7]);
        $this->assertDatabaseHas('products', ['id'=>$parent->id, 'title'=>'Keep parent', 'base_price'=>900]);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('marketplace_variant_imports', 1);
        $this->assertDatabaseCount('marketplace_variant_mappings', 1);
        $this->assertDatabaseCount('marketplace_mapping_events', 1);
    }

    public function test_whole_import_accepts_reviewed_skus_only_for_its_own_variants(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $ids=DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $url='/admin/api/marketplace/listings/'.$ids[0].'/import-product';
        $this->postJson($url,['skuOverrides'=>['999999'=>'OTHER']])->assertUnprocessable();
        $this->postJson($url,['skuOverrides'=>[(string)$ids[0]=>'SAME',(string)$ids[1]=>'SAME']])->assertConflict();
        $this->assertDatabaseCount('products',0);
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $response=$this->postJson($url,['skuOverrides'=>[(string)$ids[0]=>'REVIEWED-FIRST']])->assertOk();
        $this->assertDatabaseHas('product_variants',['product_id'=>$response->json('productId'),'sku'=>'REVIEWED-FIRST','stock'=>0]);
        $this->assertDatabaseHas('product_variants',['product_id'=>$response->json('productId'),'sku'=>'SOURCE-2','stock'=>0]);
        $this->assertDatabaseCount('product_variants',2);
    }

    public function test_mixed_review_links_existing_sku_and_creates_only_missing_draft_variant(): void
    {
        $store=$this->catalog();
        $parent=Product::create(['title'=>'Existing','handle'=>'existing','base_price'=>999,'status'=>'active']);
        $existing=$parent->variants()->create(['sku'=>'SOURCE-1','title'=>'Original','price'=>999,'stock'=>7,'enabled'=>true]);
        $ids=DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $service=app(\App\Services\Marketplace\MarketplaceReviewedImportService::class);
        $review=[(string)$ids[0]=>['productId'=>$parent->id,'variantId'=>$existing->id]];
        $result=$service->import($store,'100',42,[],$review);
        $this->assertSame(1,$result['createdVariants']);
        $this->assertSame(1,$result['linkedVariants']);
        $this->assertDatabaseCount('products',2);
        $this->assertDatabaseCount('product_variants',2);
        $this->assertDatabaseHas('product_variants',['id'=>$existing->id,'stock'=>7,'price'=>999]);
        $this->assertDatabaseHas('products',['id'=>$result['productId'],'status'=>'draft']);
        $this->assertDatabaseHas('product_variants',['product_id'=>$result['productId'],'sku'=>'SOURCE-2','stock'=>0]);
        DB::table('marketplace_variant_mappings')->delete();
        $this->assertSame($result,$service->import($store,'100',42,[],$review));
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertDatabaseCount('products',2);
    }

    public function test_mixed_review_conflict_rolls_back_prior_draft_creation(): void
    {
        $store=$this->catalog();
        $parent=Product::create(['title'=>'Existing','handle'=>'existing-conflict','base_price'=>999,'status'=>'active']);
        $existing=$parent->variants()->create(['sku'=>'SOURCE-2','title'=>'Original','price'=>999,'stock'=>7,'enabled'=>true]);
        $ids=DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $service=app(\App\Services\Marketplace\MarketplaceReviewedImportService::class);
        try {
            $service->import($store,'100',42,[],[(string)$ids[1]=>['productId'=>$parent->id+100,'variantId'=>$existing->id]]);
            $this->fail('Invalid parent must reject the entire review.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertContains($exception->getStatusCode(),[404,422]);
        }
        $this->assertDatabaseCount('products',1);
        $this->assertDatabaseCount('product_variants',1);
        $this->assertDatabaseCount('marketplace_variant_imports',0);
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertDatabaseCount('marketplace_mapping_events',0);
        $this->assertDatabaseCount('marketplace_reviewed_imports',0);
        $this->assertDatabaseHas('product_variants',['id'=>$existing->id,'stock'=>7]);
    }

    public function test_reviewed_import_endpoint_accepts_explicit_existing_links(): void
    {
        $this->catalog();
        $this->actingAs(\App\Models\User::findOrFail(42));
        $parent=Product::create(['title'=>'Existing','handle'=>'endpoint-existing','base_price'=>999,'status'=>'active']);
        $variant=$parent->variants()->create(['sku'=>'SOURCE-1','title'=>'Original','price'=>999,'stock'=>7,'enabled'=>true]);
        $id=DB::table('marketplace_listing_variants')->orderBy('id')->value('id');
        $url='/admin/api/marketplace/listings/'.$id.'/import-product';
        $this->postJson($url,['existingLinks'=>[(string)$id=>['productId'=>$parent->id,'variantId'=>$variant->id]]])
            ->assertOk()->assertJsonPath('createdVariants',1)->assertJsonPath('linkedVariants',1)->assertJsonPath('status','draft');
        $this->assertDatabaseHas('product_variants',['id'=>$variant->id,'stock'=>7]);
    }

    public function test_all_existing_review_creates_no_empty_draft_and_rejects_same_store_reuse(): void
    {
        $store=$this->catalog();
        $parent=Product::create(['title'=>'Existing','handle'=>'all-existing','base_price'=>999,'status'=>'active']);
        $first=$parent->variants()->create(['sku'=>'SOURCE-1','title'=>'First','price'=>999,'stock'=>7,'enabled'=>true]);
        $second=$parent->variants()->create(['sku'=>'SOURCE-2','title'=>'Second','price'=>888,'stock'=>8,'enabled'=>true]);
        $ids=DB::table('marketplace_listing_variants')->orderBy('id')->pluck('id');
        $service=app(\App\Services\Marketplace\MarketplaceReviewedImportService::class);
        $target=['productId'=>$parent->id,'variantId'=>$first->id];
        try {
            $service->import($store,'100',42,[],[(string)$ids[0]=>$target,(string)$ids[1]=>$target]);
            $this->fail('Same-store duplicate must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409,$exception->getStatusCode());
        }
        $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertDatabaseCount('marketplace_mapping_events',0);
        $this->assertDatabaseCount('marketplace_reviewed_imports',0);
        $result=$service->import($store,'100',42,[],[(string)$ids[0]=>$target,
            (string)$ids[1]=>['productId'=>$parent->id,'variantId'=>$second->id]]);
        $this->assertSame(['productId'=>null,'createdVariants'=>0,'linkedVariants'=>2],$result);
        $this->assertDatabaseCount('products',1);
        $this->assertDatabaseCount('product_variants',2);
        $this->assertDatabaseCount('marketplace_variant_imports',0);
        $this->assertDatabaseCount('marketplace_variant_mappings',2);
        $this->assertDatabaseHas('product_variants',['id'=>$first->id,'stock'=>7,'price'=>999]);
        $this->assertDatabaseHas('product_variants',['id'=>$second->id,'stock'=>8,'price'=>888]);
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
