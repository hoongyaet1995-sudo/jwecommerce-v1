<?php
namespace Tests\Feature;

use App\Models\{Product,User,MarketplaceStoreToken};
use App\Services\Marketplace\LazadaProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Tests\TestCase;

class LazadaProductImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_lazada_catalog_image_can_be_migrated_from_localhost_and_changed_url_invalidates_receipt(): void
    {
        config(['app.url'=>'http://127.0.0.1:8500','marketplace.publish_operations_enabled'=>true,
            'marketplace.basic_wms_enabled'=>false,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Imported image','handle'=>'imported-image','base_price'=>100]);
        $image=$product->images()->create(['path'=>'https://my-live-02.slatic.net/p/imported.jpg']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','external_shop_id'=>'456',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        $this->enablePublishing($store);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        Http::preventStrayRequests();
        Http::fake([
            '*/seller/get*'=>Http::response(['code'=>'0','data'=>['seller_id'=>'456','status'=>'ACTIVE']]),
            '*/image/migrate*'=>Http::response(['code'=>'0','data'=>['image'=>['url'=>'https://my-live-02.slatic.net/p/migrated.jpg']]]),
        ]);
        $receipts=app(\App\Services\Marketplace\MarketplaceImageReceipts::class);
        $this->assertNull($receipts->current($store,$product->id,$image->id));
        $service=app(LazadaProductImageService::class);
        $id=$service->prepare($store,$product->id,$image->id,$actor->id);
        $this->assertSame($id,(int)$receipts->current($store,$product->id,$image->id)->id);
        $this->assertSame($id,$service->prepare($store,$product->id,$image->id,$actor->id));
        Http::assertSentCount(2);
        $image->update(['path'=>'https://my-live-02.slatic.net/p/changed.jpg']);
        $this->assertNull($receipts->current($store,$product->id,$image->id));
        $image->update(['path'=>'https://my-live-02.slatic.net.evil.com/p/changed.jpg']);
        try { $service->prepare($store,$product->id,$image->id,$actor->id); $this->fail('Foreign image accepted'); }
        catch (\DomainException $e) { $this->assertStringContainsString('local catalog asset',$e->getMessage()); }
        Http::assertSentCount(2);
    }

    public function test_staging_automatically_prepares_owned_images_and_reuses_the_saved_request(): void
    {
        config(['app.url'=>'https://ecom.jwaistudio.online','marketplace.publish_operations_enabled'=>true,
            'marketplace.basic_wms_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture',
            'marketplace.lazada.test_app_key'=>'139209','marketplace.lazada.test_app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Automatic image','handle'=>'automatic-image','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'AUTO-IMAGE','price'=>1599,'stock'=>77,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Official test','external_shop_id'=>'300163725140',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        $this->enablePublishing($store);
        config(['marketplace.lazada.test_store_id'=>$store]);
        foreach ([['store',$store],['sku',$variant->id]] as [$kind,$id]) app(\App\Services\Inventory\TestDataClassificationService::class)->markTest($kind,$id,$actor,'Test automatic publishing image');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'139209','credential_source'=>'official_test',
            'access_token'=>'fixture-token','test_lease_expires_at'=>now()->addHour()]);
        Storage::fake('public');
        $path='cms/media/9285db80-1111-4111-8111-123456789013.png';Storage::disk('public')->put($path,'image fixture');
        $product->images()->create(['path'=>'/storage/'.$path]);
        Http::preventStrayRequests();
        Http::fake([
            '*/seller/get*'=>Http::response(['code'=>'0','data'=>['seller_id'=>'300163725140','short_code'=>'MY4NA1T7CK','status'=>'ACTIVE']]),
            '*/seller/warehouse/get*'=>Http::response(['code'=>'0','result'=>['success'=>true,'module'=>[['code'=>'dropshipping','status'=>'ACTIVE']]]]),
            '*/category/attributes/get*'=>Http::response(['code'=>'0','data'=>[
                ['name'=>'size_chart','label'=>'Size Chart','attribute_type'=>'normal','is_mandatory'=>1,'input_type'=>'numeric'],
                ['name'=>'color_thumbnail','attribute_type'=>'sku','is_mandatory'=>1,'input_type'=>'img'],
            ]]),
            '*/image/migrate*'=>Http::response(['code'=>'0','data'=>['image'=>['url'=>'https://my-live-02.slatic.net/p/automatic.jpg']]]),
        ]);
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $request=['product_id'=>$product->id,'request_key'=>'1285db80-1111-4111-8111-123456789045','source_revision'=>$source['revision'],
            'payload'=>[
                'jw_size_chart_image_id'=>$source['images'][0]['id'],
                'jw_attribute_image_ids'=>['lazada:variant:AUTO-IMAGE:color_thumbnail'=>$source['images'][0]['id']],
                'Request'=>['Product'=>['PrimaryCategory'=>'1','Attributes'=>[], 'Skus'=>['Sku'=>[['SellerSku'=>'AUTO-IMAGE']]]]],
            ]];
        $this->actingAs($actor);
        $id=$this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",$request)->assertOk()->json('id');
        $this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",$request)->assertOk()->assertJsonPath('id',$id);
        $this->assertDatabaseCount('marketplace_image_receipts',1);
        $this->assertDatabaseCount('marketplace_publishing_tasks',1);
        $saved=json_decode(DB::table('marketplace_publishing_tasks')->where('id',$id)->value('payload'),true);
        $this->assertSame(['https://my-live-02.slatic.net/p/automatic.jpg'],$saved['Request']['Product']['Images']['Image']);
        $this->assertSame('https://my-live-02.slatic.net/p/automatic.jpg',$saved['jw_size_chart']);
        $this->assertArrayNotHasKey('jw_size_chart_image_id',$saved);
        $this->assertArrayNotHasKey('jw_attribute_image_ids',$saved);
        $this->assertSame('https://my-live-02.slatic.net/p/automatic.jpg',$saved['Request']['Product']['Skus']['Sku'][0]['color_thumbnail']);
        $this->assertSame('0',$saved['Request']['Product']['Skus']['Sku'][0]['quantity']);
        $this->assertSame(77,$variant->fresh()->stock);
        Http::assertSentCount(6);
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/product/create'));
    }

    public function test_owned_public_image_is_migrated_and_reused_but_localhost_is_rejected(): void
    {
        config(['app.url'=>'https://ecom.jwaistudio.online','marketplace.publish_operations_enabled'=>true,
            'marketplace.basic_wms_enabled'=>false,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Image','handle'=>'lazada-owned-image','base_price'=>100]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','external_shop_id'=>'456',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        $this->enablePublishing($store);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        Storage::fake('public');
        $path='cms/media/9285db80-1111-4111-8111-123456789012.png';
        Storage::disk('public')->put($path,'owned-image-bytes');
        $image=$product->images()->create(['path'=>'/storage/'.$path]);
        $migrations=0;
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$migrations) {
            if (str_contains($request->url(),'/seller/get')) return Http::response(['code'=>'0','data'=>['seller_id'=>'456','status'=>'ACTIVE']]);
            if (str_contains($request->url(),'/image/migrate')) {
                $migrations++;
                return Http::response(['code'=>'0','data'=>['image'=>['url'=>'https://my-live-02.slatic.net/p/test.jpg']]]);
            }
            return Http::response([],500);
        });
        $service=app(LazadaProductImageService::class);
        $receipt=$service->prepare($store,$product->id,$image->id,$actor->id);
        $this->assertSame($receipt,$service->prepare($store,$product->id,$image->id,$actor->id));
        $this->actingAs($actor)->postJson("/admin/api/marketplace/stores/{$store}/products/{$product->id}/images/{$image->id}/prepare")
            ->assertOk()->assertJsonPath('id',$receipt)->assertJsonPath('providerIdentity','https://my-live-02.slatic.net/p/test.jpg');
        $this->assertSame(1,$migrations);
        $this->assertDatabaseHas('marketplace_image_receipts',['id'=>$receipt,'provider_identity'=>'https://my-live-02.slatic.net/p/test.jpg']);
        config(['app.url'=>'http://127.0.0.1:8500']);
        try {$service->prepare($store,$product->id,$image->id,$actor->id);$this->fail('Localhost passed');}
        catch (\RuntimeException $error) {$this->assertStringContainsString('HTTPS',$error->getMessage());}
        $this->assertSame(1,$migrations);
    }
    private function enablePublishing(int $storeId): void
    {
        DB::table('marketplace_store_capabilities')->insert(['store_id'=>$storeId,'catalog_read'=>true,
            'product_publish'=>true,'order_receive'=>false,'inventory_update'=>false,'fulfillment_write'=>false,
            'created_at'=>now(),'updated_at'=>now()]);
    }
}
