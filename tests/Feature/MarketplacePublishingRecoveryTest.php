<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplacePublishingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_task_can_be_cancelled_before_dispatch_and_a_new_attempt_can_be_staged(): void
    {
        config(['marketplace.read_operations_enabled'=>true,'marketplace.publish_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Pending product','handle'=>'pending-product','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $tasks=app(PublishingTasks::class);
        $first=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Pending']);
        $this->actingAs($actor)->getJson("/admin/api/marketplace/publishing-tasks/{$first}")
            ->assertOk()->assertJsonPath('status','queued')->assertJsonPath('actions.1','cancel_unsent');
        $this->postJson("/admin/api/marketplace/publishing-tasks/{$first}/cancel-unsent")
            ->assertOk()->assertJsonPath('status','rejected')->assertJsonPath('creationCertainty','not_created');
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$first,'status'=>'rejected','error_code'=>'cancelled_before_dispatch','external_item_id'=>null]);
        $next=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Pending']);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$next,'status'=>'queued','previous_task_id'=>$first]);
        $this->postJson("/admin/api/marketplace/publishing-tasks/{$first}/cancel-unsent")->assertUnprocessable();
    }

    public function test_uncertain_attempt_without_marketplace_item_does_not_block_a_new_explicit_submission(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Retry uncertain','handle'=>'retry-uncertain-direct','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $tasks=app(PublishingTasks::class);
        $first=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Test']);
        DB::table('marketplace_publishing_tasks')->where('id',$first)->update(['status'=>'uncertain']);
        $this->assertArrayNotHasKey((string)$product->id,app(\App\Services\Marketplace\PublishingStoreStatus::class)->read($store));
        $next=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Test']);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$next,'status'=>'queued','previous_task_id'=>$first]);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$first,'status'=>'uncertain']);
        $this->assertSame('review',app(\App\Services\Marketplace\PublishingStoreStatus::class)->read($store)[(string)$product->id]);
    }

    public function test_repeated_uncertain_attempts_do_not_treat_an_older_task_as_newer(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Repeat retry','handle'=>'repeat-retry','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $tasks=app(PublishingTasks::class);
        $first=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Test']);
        $previous=$first;
        $firstAuthorization=null;
        for ($i=0;$i<3;$i++) {
            DB::table('marketplace_publishing_tasks')->where('id',$previous)->update(['status'=>'uncertain']);
            $authorization=app(\App\Services\Marketplace\PublishingRecoveryAuthorizations::class)->issue($previous,$actor->id,[
                'reason'=>'Checked Seller Center; retry intentionally.','confirmationVersion'=>'v1','expectedRevision'=>1,
            ]);
            if ($i===0) $firstAuthorization=app(\App\Services\Marketplace\PublishingRecoveryAuthorizations::class)->issue($previous,$actor->id,[
                'reason'=>'Another browser tab.','confirmationVersion'=>'v1','expectedRevision'=>1,
            ]);
            $next=$tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Test'],null,$authorization['authorizationId']);
            $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$next,'previous_task_id'=>$previous,'status'=>'queued']);
            $previous=$next;
        }
        $this->expectExceptionMessage('A newer publishing attempt already exists');
        $tasks->create($store,$product->id,$actor->id,(string)\Illuminate\Support\Str::uuid(),['item_name'=>'Test'],null,$firstAuthorization['authorizationId']);
    }

    public function test_status_retains_latest_uncertain_task_details_without_blocking_a_new_submission(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Baju Kurung Joshua','handle'=>'baju-kurung-joshua','base_price'=>19900]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['Request'=>[]]);
        DB::table('marketplace_publishing_tasks')->where('id',$task)->update(['status'=>'uncertain']);

        $this->actingAs($actor)->getJson("/admin/api/marketplace/stores/{$store}/publishing-status")
            ->assertOk()->assertJsonPath("products.{$product->id}",null)
            ->assertJsonPath("tasksByProduct.{$product->id}.id",$task)
            ->assertJsonPath("tasksByProduct.{$product->id}.failure.code",'historical_reason_unavailable');

        $this->actingAs($actor)->getJson("/admin/api/marketplace/publishing-tasks/{$task}")
            ->assertOk()->assertJsonPath('creationCertainty','unknown')
            ->assertJsonPath('failure.code','historical_reason_unavailable')
            ->assertJsonPath('actions.0','check_marketplace_result');

        $otherAdmin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($otherAdmin)->getJson("/admin/api/marketplace/stores/{$store}/publishing-status")
            ->assertOk()->assertJsonPath("tasksByProduct.{$product->id}.id",$task);
        $this->getJson("/admin/api/marketplace/publishing-tasks/{$task}")
            ->assertOk()->assertJsonPath('id',$task);
    }

    public function test_uncertain_task_requires_explicit_one_use_duplicate_risk_authorization(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Retry me','handle'=>'retry-me','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['Request'=>[]]);
        DB::table('marketplace_publishing_tasks')->where('id',$task)->update(['status'=>'uncertain']);

        $url="/admin/api/marketplace/publishing-tasks/{$task}/recovery-authorizations";
        $this->actingAs($actor)->postJson($url,['reason'=>'Retry requested'])->assertUnprocessable();
        $response=$this->postJson($url,[
            'reason'=>'Checked Lazada Seller Center; no confirmed product was found.',
            'checkedMarketplace'=>true,'acceptDuplicateRisk'=>true,'confirmationVersion'=>'v1','expectedRevision'=>1,
        ])->assertCreated()->assertJsonStructure(['authorizationId','expiresAt']);
        $id=$response->json('authorizationId');
        $this->assertDatabaseHas('marketplace_publishing_recovery_authorizations',['id'=>$id,'task_id'=>$task,'actor_id'=>$actor->id]);

        $newTask=app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '1285db80-1111-4111-8111-123456789012',['Request'=>[]],null,$id);
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$newTask,'previous_task_id'=>$task,'status'=>'queued']);
        $this->assertDatabaseHas('marketplace_publishing_recovery_authorizations',['id'=>$id,'consumed_task_id'=>$newTask]);

        $this->expectException(\DomainException::class);
        app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '2285db80-1111-4111-8111-123456789012',['Request'=>[]],null,$id);
    }

    public function test_definitively_rejected_recovery_attempt_supersedes_older_uncertain_task(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Retry after review rejection','handle'=>'retry-after-review-rejection','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $uncertain=app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '9285db80-1111-4111-8111-123456789012',['Request'=>[]]);
        DB::table('marketplace_publishing_tasks')->where('id',$uncertain)->update(['status'=>'uncertain']);
        $authorization=app(\App\Services\Marketplace\PublishingRecoveryAuthorizations::class)->issue($uncertain,$actor->id,[
            'reason'=>'Checked Seller Center and accepted duplicate risk.','confirmationVersion'=>'v1','expectedRevision'=>1,
        ]);
        $rejected=app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '1285db80-1111-4111-8111-123456789012',['Request'=>[]],null,$authorization['authorizationId']);
        DB::table('marketplace_publishing_tasks')->where('id',$rejected)->update([
            'status'=>'rejected','stage'=>'review','error_code'=>'image_validation_failed',
        ]);

        $this->actingAs($actor)->getJson("/admin/api/marketplace/stores/{$store}/publishing-status")
            ->assertOk()->assertJsonMissingPath("products.{$product->id}")
            ->assertJsonPath("tasksByProduct.{$product->id}.id",$rejected);

        $retry=app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '2285db80-1111-4111-8111-123456789012',['Request'=>[]]);
        $this->assertDatabaseHas('marketplace_publishing_tasks',[
            'id'=>$retry,'status'=>'queued','previous_task_id'=>$rejected,
        ]);
    }
}
