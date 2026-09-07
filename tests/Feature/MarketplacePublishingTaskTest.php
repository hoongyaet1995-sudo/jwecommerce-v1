<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplacePublishingTaskTest extends TestCase
{
    use RefreshDatabase;

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
