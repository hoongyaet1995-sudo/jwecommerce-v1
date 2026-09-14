<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\TestDataClassificationService;
use App\Services\Marketplace\BasicPublishingDestination;
use App\Services\Marketplace\PublishingTasks;
use App\Services\Marketplace\MarketplacePublishingDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BasicPublishingDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_product_can_target_two_classified_shopee_sandbox_stores(): void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.test_store_id'=>999]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Shared product','handle'=>'shared-product','base_price'=>100]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'SHARED','price'=>100,'stock'=>77,'enabled'=>true]);
        app(TestDataClassificationService::class)->markTest('sku',$variant->id,$actor,'Sandbox test');
        $tasks=[];
        foreach (['1001','1002'] as $shop) {
            $id=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
                'external_shop_id'=>$shop,'region'=>'MY','environment'=>'sandbox','status'=>'connected']);
            app(TestDataClassificationService::class)->markTest('store',$id,$actor,'Sandbox test');
            MarketplaceStoreToken::create(['store_id'=>$id,'application_id'=>'123','access_token'=>'fixture','access_expires_at'=>now()->addHour()]);
            app(BasicPublishingDestination::class)->check($id,$product->id);
            $tasks[]=app(PublishingTasks::class)->create($id,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',[]);
        }
        $this->assertNotSame($tasks[0],$tasks[1]);
        $this->assertSame(77,$variant->fresh()->stock);
        DB::table('marketplace_stores')->where('id',$id)->update(['environment'=>'live']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BasicPublishingDestination::class)->check($id,$product->id);
    }

    public function test_lazada_staging_persists_server_master_values_and_zero_stock(): void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.publish_operations_enabled'=>true,
            'marketplace.lazada.app_key'=>'139209','marketplace.lazada.enabled'=>true,'marketplace.lazada.app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Database title','handle'=>'bound-publishing','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'BOUND','price'=>1599,'stock'=>77,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','external_shop_id'=>'300163725140',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        config(['marketplace.lazada.test_store_id'=>$store]);
        foreach ([['store',$store],['sku',$variant->id]] as [$type,$id]) app(TestDataClassificationService::class)->markTest($type,$id,$actor,'Test fixture');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'139209','credential_source'=>'official_test',
            'access_token'=>'fixture-token','test_lease_expires_at'=>now()->addHour(),'access_expires_at'=>now()->addHour()]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $path='cms/media/9285db80-1111-4111-8111-123456789012.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path,'owned image');
        $image=$product->images()->create(['path'=>'/storage/'.$path]);
        $hash=\App\Services\Marketplace\MarketplaceImageReceipts::fingerprint(DB::table('marketplace_stores')->find($store),'owned image',$image->path);
        app(\App\Services\Marketplace\MarketplaceImageReceipts::class)->record($store,$product->id,$image->id,$actor->id,$hash,'https://my-live-02.slatic.net/p/owned.jpg');
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        Http::preventStrayRequests();
        $warehouseRows=[['code'=>'dropshipping','status'=>'ACTIVE'],['code'=>'MY-2','status'=>'ACTIVE']];
        Http::fake(['*/seller/warehouse/get*'=>function()use(&$warehouseRows){return Http::response(['code'=>'0','result'=>['success'=>true,'module'=>$warehouseRows]]);}]);
        $this->actingAs($actor)->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",[
            'product_id'=>$product->id,'request_key'=>'1285db80-1111-4111-8111-123456789019','source_revision'=>$source['revision'],
            'payload'=>['Request'=>['Product'=>['Attributes'=>[], 'Skus'=>['Sku'=>[['SellerSku'=>'FOREIGN']]]]]],
        ])->assertStatus(422)->assertJsonValidationErrors('publishing');
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
        $response=$this->actingAs($actor)->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",[
            'product_id'=>$product->id,'request_key'=>'1285db80-1111-4111-8111-123456789012','source_revision'=>$source['revision'],
            'payload'=>['Request'=>['Product'=>['Attributes'=>['name'=>'Browser title'],
                'Skus'=>['Sku'=>[['SellerSku'=>'BOUND','price'=>'1.00','quantity'=>'77']]]]]],
        ])->assertOk();
        $task=DB::table('marketplace_publishing_tasks')->find($response->json('id'));
        $saved=json_decode($task->payload,true)['Request']['Product'];
        $this->assertSame('Database title',$saved['Attributes']['name']);
        $this->assertSame(['https://my-live-02.slatic.net/p/owned.jpg'],$saved['Images']['Image'] ?? null);
        $this->assertSame('15.99',$saved['Skus']['Sku'][0]['price']);
        $this->assertSame('0',$saved['Skus']['Sku'][0]['quantity']);
        $this->assertSame(['MultiWarehouseInventory'=>[['Quantity'=>'0','WarehouseCode'=>'MY-2'],['Quantity'=>'0','WarehouseCode'=>'dropshipping']]],
            $saved['Skus']['Sku'][0]['MultiWarehouseInventories']??null);
        $this->assertSame(77,$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_balances',0);
        Http::assertSentCount(1);
        $warehouses=app(\App\Services\Marketplace\LazadaPublishingWarehouses::class);
        $warehouses->verify($store,json_decode($task->payload,true));
        $warehouseRows[]=['code'=>'MY-NEW','status'=>'ACTIVE'];
        try {$warehouses->verify($store,json_decode($task->payload,true));$this->fail('Added warehouse accepted without new review');}
        catch (\RuntimeException $error) {$this->assertStringContainsString('changed',$error->getMessage());}
        \Illuminate\Support\Facades\Storage::disk('public')->put($path,'changed after review');
        try {app(MarketplacePublishingDispatchService::class)->dispatch($task->id);$this->fail('Stale image dispatched');}
        catch (\Illuminate\Validation\ValidationException $error) {$this->assertArrayHasKey('images',$error->errors());}
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task->id,'status'=>'rejected']);
        Http::assertSentCount(3);
    }

    public function test_valid_local_eligibility_still_requires_fresh_provider_identity_before_creation(): void
    {
        config(['marketplace.basic_wms_enabled'=>true,'marketplace.publish_operations_enabled'=>true,
            'marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'basic-publishing','base_price'=>100]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'JW-BASIC','price'=>100,'stock'=>77,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','external_shop_id'=>'300163725140',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        config(['marketplace.lazada.test_store_id'=>$store]);
        foreach ([['store',$store],['sku',$variant->id]] as [$type,$id]) app(TestDataClassificationService::class)->markTest($type,$id,$actor,'Test fixture');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'139209','credential_source'=>'official_test',
            'access_token'=>'fixture-token','test_lease_expires_at'=>now()->addHour(),'access_expires_at'=>now()->addHour()]);
        app(BasicPublishingDestination::class)->check($store,$product->id);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],'Skus'=>['Sku'=>[['SellerSku'=>'JW-BASIC','quantity'=>'0','price'=>'1.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',$payload);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>['seller_id'=>'OTHER','short_code'=>'MY4NA1T7CK','status'=>'ACTIVE']])]);
        try {app(MarketplacePublishingDispatchService::class)->dispatch($task);$this->fail('Wrong seller accepted');}
        catch (\RuntimeException $error) {$this->assertStringContainsString('identity',$error->getMessage());}
        Http::assertSent(fn($request)=>str_contains($request->url(),'/seller/get'));
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/product/create'));
        $this->assertSame(77,$variant->fresh()->stock);
        $payload['Request']['Product']['Skus']['Sku'][0]['quantity']='77';
        $blocked=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789013',$payload);
        Http::fake();
        try {app(MarketplacePublishingDispatchService::class)->dispatch($blocked);$this->fail('Positive stock published');}
        catch (\DomainException $error) {$this->assertStringContainsString('zero',$error->getMessage());}
        Http::assertNothingSent();
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$blocked,'status'=>'rejected']);
    }
}
