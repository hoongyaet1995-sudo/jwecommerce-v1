<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\PublishingTasks;
use App\Services\Marketplace\MarketplacePublishingDispatchService;
use App\Models\MarketplaceStoreToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplacePublishingTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazada_stage_reloads_schema_and_rejects_forged_attributes_before_saving(): void
    {
        config(['app.url'=>'https://ecom.jwaistudio.online','marketplace.publish_operations_enabled'=>true,
            'marketplace.basic_wms_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture',
            'marketplace.lazada.test_app_key'=>'139209','marketplace.lazada.test_app_secret'=>'fixture']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Reviewed attributes','handle'=>'reviewed-attributes','base_price'=>1599]);
        $variant=$product->variants()->create(['title'=>'Blue','sku'=>'BLUE-1','price'=>1599,'stock'=>2,'enabled'=>true]);
        $image=$product->images()->create(['path'=>'https://my-live-02.slatic.net/p/owned.jpg']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Official test','external_shop_id'=>'300163725140',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Attribute staging test');
        config(['marketplace.lazada.test_store_id'=>$store]);
        foreach ([['store',$store],['sku',$variant->id]] as [$kind,$id]) app(\App\Services\Inventory\TestDataClassificationService::class)->markTest($kind,$id,$actor,'Attribute staging test');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'139209','credential_source'=>'official_test',
            'access_token'=>'fixture-token','test_lease_expires_at'=>now()->addHour()]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(),'/category/attributes/get')) return Http::response(['code'=>'0','data'=>[
                ['name'=>'name','attribute_type'=>'normal','is_mandatory'=>1,'input_type'=>'text'],
                ['name'=>'color_family','attribute_type'=>'sku','is_mandatory'=>1,'is_sale_prop'=>1,'input_type'=>'singleSelect','options'=>[['en_name'=>'Blue']]],
                ['name'=>'color_thumbnail','attribute_type'=>'sku','is_mandatory'=>1,'input_type'=>'img'],
            ]]);
            if (str_contains($request->url(),'/seller/warehouse/get')) return Http::response(['code'=>'0','result'=>['success'=>true,'module'=>[['code'=>'dropshipping','status'=>'ACTIVE']]]]);
            if (str_contains($request->url(),'/image/migrate')) return Http::response(['code'=>'0','data'=>['image'=>['url'=>'https://my-live-02.slatic.net/p/owned.jpg']]]);
            return Http::response(['code'=>'0','data'=>['seller_id'=>'300163725140','short_code'=>'MY4NA1T7CK','status'=>'ACTIVE']]);
        });
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $payload=['jw_attribute_image_ids'=>['lazada:variant:BLUE-1:color_thumbnail'=>$image->id],
            'Request'=>['Product'=>['PrimaryCategory'=>'100','Attributes'=>['name'=>'Reviewed attributes','forged'=>'unsafe'],
                'Skus'=>['Sku'=>[['SellerSku'=>'BLUE-1','price'=>'15.99','quantity'=>'0','color_family'=>'Blue']]],
                'Debug'=>['jw_secret'=>'unsafe']]]];
        $request=['product_id'=>$product->id,'request_key'=>'1185db80-1111-4111-8111-123456789045','source_revision'=>$source['revision'],'payload'=>$payload];

        $this->actingAs($actor)->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",$request)->assertUnprocessable();
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);

        unset($request['payload']['Request']['Product']['Attributes']['forged']);
        unset($request['payload']['Request']['Product']['Debug']);
        $request['request_key']='2185db80-1111-4111-8111-123456789045';
        $this->postJson("/admin/api/marketplace/stores/{$store}/publishing-tasks",$request)->assertOk();
        $saved=json_decode(DB::table('marketplace_publishing_tasks')->value('payload'),true);
        $this->assertArrayNotHasKey('jw_attribute_image_ids',$saved);
        $this->assertSame('https://my-live-02.slatic.net/p/owned.jpg',$saved['Request']['Product']['Skus']['Sku'][0]['color_thumbnail']);
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/product/create'));
    }

    public function test_dispatch_rejects_nested_internal_fields_before_any_provider_request(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Nested guard','handle'=>'nested-internal-guard','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'live','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Nested internal guard');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Attributes'=>['name'=>'Nested guard','meta'=>['jw_secret'=>'x']],
            'Skus'=>['Sku'=>[['SellerSku'=>'NESTED-1','quantity'=>'0','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'6285db80-1111-4111-8111-123456789012',$payload);
        Http::preventStrayRequests();

        try {
            app(MarketplacePublishingDispatchService::class)->dispatch($task);
            $this->fail('A nested internal field reached the provider boundary.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Internal publishing fields', $error->getMessage());
        }

        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'rejected']);
        Http::assertNothingSent();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Transport/task tests exercise the later publishing contract; Basic destination checks have separate coverage.
        config(['marketplace.basic_wms_enabled'=>false]);
    }

    public function test_restored_shopee_model_payload_ignores_object_key_order_but_preserves_variant_order_and_types(): void
    {
        $tasks=app(PublishingTasks::class);
        $model=['tier_index'=>[0],'model_sku'=>'RED','original_price'=>10.5,'seller_stock'=>[['stock'=>0]]];
        $saved=$model;ksort($saved);
        $this->assertTrue($tasks->payloadsMatch(['model'=>[$model]],['model'=>[$saved]]));
        $other=$model;$other['model_sku']='BLUE';$other['tier_index']=[1];
        $this->assertFalse($tasks->payloadsMatch(['model'=>[$model,$other]],['model'=>[$other,$model]]));
        $saved['seller_stock'][0]['stock']='0';
        $this->assertFalse($tasks->payloadsMatch(['model'=>[$model]],['model'=>[$saved]]));
    }

    public static function catalogReadbackCases(): array { return [[true],[false]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('catalogReadbackCases')]
    public function test_reviewed_lazada_publication_reads_back_and_links_the_confirmed_variant(bool $readable): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'published-readback','base_price'=>1000]);
        $variant=$product->variants()->create(['title'=>'Default','sku'=>'TEST','price'=>1000,'stock'=>17,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'live','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Publishing task fixture');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],'Skus'=>['Sku'=>[['SellerSku'=>'TEST','quantity'=>'0','price'=>'10.00']]]]]];
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'7285db80-1111-4111-8111-123456789012',$payload,$source);
        Http::preventStrayRequests();
        Http::fake([
            '*/product/create*'=>Http::response(['code'=>'0','data'=>['item_id'=>1001,'sku_list'=>[['seller_sku'=>'TEST','sku_id'=>2001]]]]),
            '*/product/item/get*'=>$readable ? Http::response(['code'=>'0','data'=>['item_id'=>1001,'attributes'=>['name'=>'Test'],
                'skus'=>[['SkuId'=>2001,'SellerSku'=>'TEST','quantity'=>0,'price'=>'10.00']]]]) : Http::response(['code'=>'ServiceTimeout'],503),
        ]);
        app(MarketplacePublishingDispatchService::class)->dispatch($task);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'succeeded','external_item_id'=>'1001']);
        if ($readable) $this->assertDatabaseHas('marketplace_variant_mappings',['store_id'=>$store,'product_variant_id'=>$variant->id]);
        else $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertSame(17,$variant->fresh()->stock);
        Http::assertSentCount(2);
    }

    public function test_uncertain_dispatch_returns_recovery_guidance_without_provider_details(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'uncertain-api','base_price'=>100]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'live','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Publishing task fixture');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],'Skus'=>['Sku'=>[['SellerSku'=>'TEST','quantity'=>'0','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'3285db80-1111-4111-8111-123456789012',$payload);
        Http::fake(['*'=>Http::response(['code'=>'provider-secret-error','message'=>'fixture-token'],500)]);
        $response=$this->actingAs($actor)->postJson("/admin/api/marketplace/publishing-tasks/{$task}/dispatch")
            ->assertStatus(409)->assertJsonPath('code','publishing_result_uncertain')->assertJsonPath('taskId',$task)
            ->assertJsonPath('retryAllowed',false);
        $this->assertStringNotContainsString('fixture-token',$response->getContent());
        $this->assertStringNotContainsString('provider-secret-error',$response->getContent());
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'uncertain']);
        $this->postJson("/admin/api/marketplace/publishing-tasks/{$task}/dispatch")->assertStatus(409)
            ->assertJsonPath('code','publishing_dispatch_unavailable');
        Http::assertSentCount(1);
    }

    public function test_failure_after_provider_confirmation_preserves_identity_and_uncertainty(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'persistence-failure','base_price'=>100]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'live','status'=>'connected']);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Publishing task fixture');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token',
            'access_expires_at'=>now()->addHour()]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],'Skus'=>['Sku'=>[['SellerSku'=>'TEST','quantity'=>'0','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'5285db80-1111-4111-8111-123456789012',$payload);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>['item_id'=>1001,'sku_list'=>[['seller_sku'=>'TEST','sku_id'=>2001]]]])]);
        $injected=false;
        DB::connection()->beforeExecuting(function ($sql,$bindings) use (&$injected) {
            if (!$injected&&str_starts_with($sql,'update')&&str_contains($sql,'marketplace_publishing_tasks')&&in_array('succeeded',$bindings,true)) {
                $injected=true;
                throw new \RuntimeException('Injected database failure');
            }
        });
        try { app(MarketplacePublishingDispatchService::class)->dispatch($task); $this->fail('Failure not surfaced'); }
        catch (\App\Services\Marketplace\PublishingResultUncertain $error) { $this->assertNull($error->getPrevious()); }
        $this->assertTrue($injected);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'uncertain','external_item_id'=>'1001']);
        $this->assertSame(['item_id'=>'1001','sku_ids'=>['TEST'=>'2001']],json_decode(DB::table('marketplace_publishing_tasks')->where('id',$task)->value('confirmed_result'),true));
        Http::assertSentCount(1);
    }

    public function test_publishing_endpoints_refuse_staging_or_dispatch_before_the_publish_gate_is_enabled(): void
    {
        config(['marketplace.read_operations_enabled'=>true, 'marketplace.publish_operations_enabled'=>false]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($actor)->postJson('/admin/api/marketplace/stores/999/publishing-tasks',[])
            ->assertStatus(503)->assertJsonPath('code','marketplace_publishing_disabled');
        $this->actingAs($actor)->postJson('/admin/api/marketplace/publishing-tasks/999/dispatch')
            ->assertStatus(503)->assertJsonPath('code','marketplace_publishing_disabled');
    }

    public function test_general_operations_switch_cannot_enable_product_publishing(): void
    {
        config(['marketplace.operations_enabled'=>true, 'marketplace.publish_operations_enabled'=>false]);
        Http::fake();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/marketplace/stores/999/publishing-tasks', [])
            ->assertStatus(503)->assertJsonPath('code','marketplace_publishing_disabled');
        $this->postJson('/admin/api/marketplace/publishing-tasks/999/dispatch')
            ->assertStatus(503)->assertJsonPath('code','marketplace_publishing_disabled');
        $this->assertDatabaseCount('marketplace_publishing_tasks',0);
        Http::assertNothingSent();
    }

    public function test_current_lazada_publishing_task_dispatches_once_and_persists_confirmed_item_identity(): void
    {
        config(['marketplace.publish_operations_enabled'=>true, 'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'JW Sandbox test','handle'=>'jw-sandbox-test','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Lazada MY','external_shop_id'=>'2001',
            'region'=>'MY','environment'=>'live','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Size-chart publishing test');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh',
            'access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay()]);
        $payload=['jw_size_chart'=>'https://my-live-02.slatic.net/p/size-chart.png','Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'JW-SBX Product'],'Skus'=>['Sku'=>[['SellerSku'=>'JW-SBX-001','quantity'=>'2','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',$payload);
        Http::preventStrayRequests();
        Http::fake(function($request){
            if(str_contains($request->url(),'/product/create')){
                $body=json_decode($request['payload']??'{}',true);
                foreach(array_keys($body) as $key)$this->assertFalse(str_starts_with((string)$key,'jw_'));
                return Http::response(['code'=>'0','data'=>['item_id'=>1001,'sku_list'=>[['seller_sku'=>'JW-SBX-001','sku_id'=>2001]]]]);
            }
            return Http::response(['code'=>'0','data'=>[]]);
        });

        $result=app(MarketplacePublishingDispatchService::class)->dispatch($task);

        $this->assertSame('succeeded',$result['status']);
        $this->assertSame('1001',$result['external_item_id']);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'succeeded','external_item_id'=>'1001']);
        $this->assertSame(['item_id'=>'1001','sku_ids'=>['JW-SBX-001'=>'2001']],
            json_decode(DB::table('marketplace_publishing_tasks')->where('id',$task)->value('confirmed_result') ?? 'null',true));
        Http::assertSentCount(2);
    }

    public function test_lazada_size_chart_failure_preserves_created_parent_and_requires_reconciliation(): void
    {
        config(['marketplace.publish_operations_enabled'=>true, 'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'JW Size Chart retry guard','handle'=>'jw-size-chart-retry-guard','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Lazada MY','external_shop_id'=>'2001',
            'region'=>'MY','environment'=>'live','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        app(\App\Services\Marketplace\MarketplaceStoreAccess::class)->setProductPublishing($store,true,$actor->id,'Size-chart retry guard');
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh',
            'access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay()]);
        $payload=['jw_size_chart'=>'https://my-live-02.slatic.net/p/size-chart.png','Request'=>['Product'=>['PrimaryCategory'=>'10002019',
            'Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'JW Size Chart retry guard'],'Skus'=>['Sku'=>[['SellerSku'=>'JW-SBX-002','quantity'=>'0','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789099',$payload);
        Http::preventStrayRequests();
        Http::fake([
            'api.lazada.com.my/rest/product/create*'=>Http::response(['code'=>'0','data'=>['item_id'=>1002,
                'sku_list'=>[['seller_sku'=>'JW-SBX-002','sku_id'=>2002]]]]),
            'api.lazada.com.my/rest/size/chart/batch/update*'=>Http::response(['code'=>'500','message'=>'temporary provider failure']),
        ]);

        try {
            app(MarketplacePublishingDispatchService::class)->dispatch($task);
            $this->fail('A failed size chart update must require reconciliation.');
        } catch (\App\Services\Marketplace\PublishingResultUncertain) {
            $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'uncertain','external_item_id'=>'1002']);
        }
        Http::assertSentCount(2);
    }

    public function test_interrupted_processing_becomes_uncertain_without_requeueing_or_losing_parent(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'interrupted-publish','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $id=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['item_name'=>'Test']);
        $tasks->transition($id,'queued','processing');
        $tasks->recordParent($id,'846055463');
        $this->assertSame(0,$tasks->markInterrupted(now()->subMinutes(15)));
        DB::table('marketplace_publishing_tasks')->where('id',$id)->update(['updated_at'=>now()->subMinutes(20)]);
        $this->assertSame(1,$tasks->markInterrupted(now()->subMinutes(15)));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'uncertain','external_item_id'=>'846055463']);
        $this->assertFalse($tasks->transition($id,'processing','succeeded','846055463'));
        $this->assertSame(0,$tasks->markInterrupted(now()->subMinutes(15)));
    }

    public function test_disconnected_store_cannot_start_a_queued_listing(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'disconnected-publish','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $id=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['item_name'=>'Test']);
        DB::table('marketplace_stores')->where('id',$store)->update(['status'=>'disconnected']);
        $this->assertFalse($tasks->transition($id,'queued','processing'));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'queued']);
    }

    public function test_parent_checkpoint_is_not_full_success_and_blocks_new_parent_after_failure(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'parent-checkpoint','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $id=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['item_name'=>'Test']);
        $tasks->transition($id,'queued','processing');
        $this->assertTrue($tasks->recordParent($id,'846055463'));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'processing','external_item_id'=>'846055463']);
        $this->assertFalse($tasks->recordParent($id,'999999'));
        $this->assertFalse($tasks->transition($id,'processing','succeeded','999999'));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'processing','external_item_id'=>'846055463']);
        $tasks->transition($id,'processing','rejected');
        $this->expectException(\DomainException::class);
        $tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789013',['item_name'=>'Test']);
    }

    public function test_new_request_key_cannot_duplicate_an_unresolved_listing_attempt(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'duplicate-attempt','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $id=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['item_name'=>'Test']);
        foreach (['queued','processing','uncertain','succeeded'] as $status) {
            DB::table('marketplace_publishing_tasks')->where('id',$id)->update(['status'=>$status]);
            try {
                $tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789013',['item_name'=>'Test']);
                $this->fail('Existing listing attempt must block a second create.');
            } catch (\DomainException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertDatabaseCount('marketplace_publishing_tasks',1);
        DB::table('marketplace_publishing_tasks')->where('id',$id)->update(['status'=>'rejected']);
        $new=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789013',['item_name'=>'Corrected']);
        $this->assertNotSame($id,$new);
    }

    public function test_stale_worker_cannot_overwrite_persisted_result(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'transition-test','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $id=$tasks->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['item_name'=>'Test']);
        $this->assertTrue($tasks->transition($id,'queued','processing'));
        $this->assertFalse($tasks->transition($id,'queued','processing'));
        $this->assertTrue($tasks->transition($id,'processing','uncertain'));
        $this->assertFalse($tasks->transition($id,'processing','succeeded','846055463'));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'uncertain','external_item_id'=>null]);
        $this->assertTrue($tasks->transition($id,'uncertain','succeeded','846055463'));
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'succeeded','external_item_id'=>'846055463']);
        $this->assertFalse($tasks->transition($id,'uncertain','rejected'));
    }

    public function test_duplicate_submission_retains_task_and_changed_payload_is_rejected(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'publish-test','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $tasks=app(PublishingTasks::class);
        $key='9285db80-1111-4111-8111-123456789012';
        $payload=['item_name'=>'Test','original_price'=>10];
        $id=$tasks->create($store,$product->id,$actor->id,$key,$payload);
        $this->assertSame($id,$tasks->create($store,$product->id,$actor->id,$key,array_reverse($payload,true)));
        $this->assertDatabaseCount('marketplace_publishing_tasks',1);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$id,'status'=>'queued','external_item_id'=>null]);
        $this->expectException(\DomainException::class);
        $tasks->create($store,$product->id,$actor->id,$key,['item_name'=>'Different']);
    }
}
