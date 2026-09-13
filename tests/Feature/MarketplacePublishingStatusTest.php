<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Marketplace\PublishingTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplacePublishingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_lost_submission_receipt_can_be_recovered_without_another_write(): void
    {
        config(['marketplace.read_operations_enabled'=>true,'marketplace.publish_operations_enabled'=>false]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'recover-publish','base_price'=>1000]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        $key='9285db80-1111-4111-8111-123456789012';
        $id=app(PublishingTasks::class)->create($store,$product->id,$actor->id,$key,['private'=>'not-visible']);
        $url="/admin/api/marketplace/stores/{$store}/publishing-request/{$key}";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonPath('id',$id)
            ->assertJsonPath('status','queued')->assertDontSee('not-visible');
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertNotFound();
        $this->actingAs($actor)->getJson(str_replace((string)$store.'/publishing', '99999/publishing', $url))->assertNotFound();
        $this->assertDatabaseCount('marketplace_publishing_tasks',1);
    }

    public function test_status_is_admin_only_and_excludes_submission_payload(): void
    {
        config(['marketplace.read_operations_enabled'=>true]);
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'status-publish','base_price'=>1000,'status'=>'draft']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Sandbox',
            'region'=>'MY','environment'=>'sandbox','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        $id=app(PublishingTasks::class)->create($store,$product->id,$actor->id,
            '9285db80-1111-4111-8111-123456789012',['private_submission'=>'must-not-return']);
        $url='/admin/api/marketplace/publishing-tasks/'.$id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonPath('status','queued')
            ->assertJsonPath('id',$id)->assertJsonPath('externalItemId',null)
            ->assertJsonMissingPath('payload')->assertDontSee('must-not-return')
            ->assertHeader('Cache-Control','no-store, private');
        DB::table('marketplace_publishing_tasks')->where('id',$id)->update(['status'=>'uncertain','external_item_id'=>'846055463']);
        $this->getJson($url)->assertOk()->assertJsonPath('status','uncertain')->assertJsonPath('externalItemId','846055463');
        $this->getJson('/admin/api/marketplace/publishing-tasks/999999')->assertNotFound();
        config(['marketplace.operations_enabled'=>false,'marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
    }
}
