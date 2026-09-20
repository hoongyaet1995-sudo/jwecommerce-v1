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

    public function test_status_exposes_latest_reviewable_task_and_legacy_reason_honestly(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Baju Kurung Joshua','handle'=>'baju-kurung-joshua','base_price'=>19900]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'9285db80-1111-4111-8111-123456789012',['Request'=>[]]);
        DB::table('marketplace_publishing_tasks')->where('id',$task)->update(['status'=>'uncertain']);

        $this->actingAs($actor)->getJson("/admin/api/marketplace/stores/{$store}/publishing-status")
            ->assertOk()->assertJsonPath("products.{$product->id}",'review')
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
}
