<?php
namespace Tests\Feature;

use App\Models\{Product,User,MarketplaceStoreToken};
use App\Services\Marketplace\ShopeeProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Tests\TestCase;

class ShopeeProductImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_endpoint_requires_login_and_publish_flag(): void
    {
        Http::fake();
        $url='/admin/api/marketplace/stores/1/products/1/images/1/prepare';
        $this->postJson($url)->assertUnauthorized();
        config(['marketplace.publish_operations_enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson($url)
            ->assertStatus(503)->assertJsonPath('code','marketplace_publishing_disabled');
        Http::assertNothingSent();
    }

    public function test_owned_image_is_uploaded_once_and_changed_bytes_require_new_upload(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.basic_wms_enabled'=>false,
            'marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Image','handle'=>'image-upload','base_price'=>100]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','external_shop_id'=>'456',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Size-chart image publishing test');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        Storage::fake('public');
        $path='cms/media/9285db80-1111-4111-8111-123456789012.png';
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9Z8AAAAASUVORK5CYII=');
        Storage::disk('public')->put($path,$bytes);
        $image=$product->images()->create(['path'=>'/storage/'.$path]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'OWNED-JW','price'=>1250,'stock'=>17,'enabled'=>true]);
        $uploads=0;
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$uploads) {
            if (str_contains($request->url(),'get_shop_info')) return Http::response(['error'=>'','shop_id'=>456,'shop_name'=>'Test','region'=>'MY']);
            if (str_contains($request->url(),'upload_image')) return Http::response(['error'=>'','response'=>['image_info'=>['image_id'=>'image-'.(++$uploads)]]]);
            if (str_contains($request->url(),'get_category')) return Http::response(['response'=>['category_list'=>[
                ['category_id'=>101,'parent_category_id'=>100,'original_category_name'=>'Images','display_category_name'=>'Images','has_children'=>false],
            ]]]);
            if (str_contains($request->url(),'get_attribute_tree')) return Http::response(['response'=>['list'=>[['category_id'=>101,'attribute_tree'=>[
                ['attribute_id'=>10,'mandatory'=>true,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1],
                    'attribute_value_list'=>[['value_id'=>7,'name'=>'Platform Metal']]],
            ]]]]]);
            if (str_contains($request->url(),'get_brand_list')) return Http::response(['response'=>[
                'brand_list'=>[],'has_next_page'=>false,'next_offset'=>0,'is_mandatory'=>false,'input_type'=>'DROP_DOWN',
            ]]);
            if (str_contains($request->url(),'get_channel_list')) return Http::response(['response'=>['logistics_channel_list'=>[
                ['logistics_channel_id'=>24000,'logistics_channel_name'=>'Shopee Sandbox','enabled'=>true,'fee_type'=>'SIZE_INPUT',
                    'weight_limit'=>['item_min_weight'=>0,'item_max_weight'=>20],
                    'item_max_dimension'=>['height'=>30,'width'=>30,'length'=>30,'unit'=>'cm','dimension_sum'=>0]],
            ]]]);
            return Http::response([],500);
        });
        $service=app(ShopeeProductImageService::class);
        $first=$service->prepare($store,$product->id,$image->id,$actor->id);
        $this->assertSame($first,$service->prepare($store,$product->id,$image->id,$actor->id));
        $url="/admin/api/marketplace/stores/{$store}/products/{$product->id}/images/{$image->id}/prepare";
        $this->actingAs($actor)->postJson($url)->assertOk()->assertJsonPath('id',$first)
            ->assertJsonPath('providerIdentity','image-1')->assertJsonPath('status','succeeded');
        $this->postJson($url,['url'=>'https://example.com/arbitrary.jpg'])->assertStatus(422);
        $this->assertSame(1,$uploads);
        $this->assertSame('image-1',app(\App\Services\Marketplace\MarketplaceImageReceipts::class)->current($store,$product->id,$image->id)->provider_identity);
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $stageUrl="/admin/api/marketplace/stores/{$store}/publishing-tasks";
        // Turn on Basic only after isolated destination fixtures have been classified.
        DB::table('marketplace_stores')->where('id',$store)->update(['data_classification'=>'test','classified_by'=>$actor->id,'classified_at'=>now()]);
        DB::table('product_variants')->where('id',$variant->id)->update(['data_classification'=>'test','classified_by'=>$actor->id,'classified_at'=>now()]);
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.shopee.test_store_id'=>$store]);
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $payload=['item_name'=>'Browser override','category_id'=>101,'item_status'=>'UNLIST','condition'=>'NEW','weight'=>1,
            'dimension'=>['package_height'=>10,'package_width'=>10,'package_length'=>10],
            'attribute_list'=>[['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>999,'original_value_name'=>'Forged']]]],
            'logistic_info'=>[['logistic_id'=>24000,'is_free'=>false]],
            'jw_size_chart_image_id'=>$image->id,'original_price'=>999,'original_stock'=>500,'image'=>['image_id_list'=>['foreign-image']]];
        $this->postJson($stageUrl,['product_id'=>$product->id,'source_revision'=>$source['revision'],
            'request_key'=>'6285db80-1111-4111-8111-123456789012','payload'=>$payload])->assertUnprocessable();
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
        $payload['attribute_list'][0]['attribute_value_list']=[['value_id'=>7,'original_value_name'=>'Forged']];
        $this->postJson($stageUrl,['product_id'=>$product->id,'source_revision'=>$source['revision'],
            'request_key'=>'7285db80-1111-4111-8111-123456789012','payload'=>$payload])->assertOk();
        $saved=json_decode(DB::table('marketplace_publishing_tasks')->value('payload'),true);
        $this->assertSame('Image',$saved['item_name']);
        $this->assertSame(12.5,$saved['original_price']);
        $this->assertSame(['image-1'],$saved['image']['image_id_list']);
        $this->assertSame('image-1',$saved['size_chart']);
        $this->assertSame('Platform Metal',$saved['attribute_list'][0]['attribute_value_list'][0]['original_value_name']);
        $this->assertArrayNotHasKey('jw_size_chart_image_id',$saved);
        $this->assertSame([['stock'=>0]],$saved['seller_stock']);
        $this->assertSame(17,$variant->fresh()->stock);
        $this->assertSame(1,$uploads);
        Storage::disk('public')->put($path,$bytes.'changed');
        $this->assertNull(app(\App\Services\Marketplace\MarketplaceImageReceipts::class)->current($store,$product->id,$image->id));
        $taskId=(int)DB::table('marketplace_publishing_tasks')->value('id');
        try { app(\App\Services\Marketplace\MarketplacePublishingDispatchService::class)->dispatch($taskId); $this->fail('Stale image dispatched'); }
        catch (\DomainException $error) { $this->assertStringContainsString('images changed',$error->getMessage()); }
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$taskId,'status'=>'rejected','external_item_id'=>null]);
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/add_item'));
        $this->assertNotSame($first,$service->prepare($store,$product->id,$image->id,$actor->id));
        $this->assertSame(2,$uploads);
        $this->assertDatabaseCount('marketplace_image_receipts',2);
        config(['marketplace.publish_operations_enabled'=>false]);
        $this->expectException(\RuntimeException::class);
        $service->prepare($store,$product->id,$image->id,$actor->id);
    }
}
