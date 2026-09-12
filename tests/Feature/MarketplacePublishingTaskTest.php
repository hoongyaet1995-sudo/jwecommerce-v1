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

    public function test_failure_after_provider_confirmation_preserves_identity_and_uncertainty(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'persistence-failure','base_price'=>100]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'live','status'=>'connected']);
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

    public function test_current_lazada_publishing_task_dispatches_once_and_persists_confirmed_item_identity(): void
    {
        config(['marketplace.publish_operations_enabled'=>true, 'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'JW Sandbox test','handle'=>'jw-sandbox-test','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Lazada MY','external_shop_id'=>'2001',
            'region'=>'MY','environment'=>'live','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','refresh_token'=>'fixture-refresh',
            'access_expires_at'=>now()->addHour(),'refresh_expires_at'=>now()->addDay()]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019','Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'JW-SBX Product'],'Skus'=>['Sku'=>[['SellerSku'=>'JW-SBX-001','quantity'=>'2','price'=>'10.00']]]]]];
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',$payload);
        Http::preventStrayRequests();
        Http::fake(['api.lazada.com.my/rest/product/create*'=>Http::response(['code'=>'0','data'=>['item_id'=>1001,
            'sku_list'=>[['seller_sku'=>'JW-SBX-001','sku_id'=>2001]]]])]);

        $result=app(MarketplacePublishingDispatchService::class)->dispatch($task);

        $this->assertSame('succeeded',$result['status']);
        $this->assertSame('1001',$result['external_item_id']);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'succeeded','external_item_id'=>'1001']);
        $this->assertSame(['item_id'=>'1001','sku_ids'=>['JW-SBX-001'=>'2001']],
            json_decode(DB::table('marketplace_publishing_tasks')->where('id',$task)->value('confirmed_result') ?? 'null',true));
        Http::assertSentCount(1);
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
