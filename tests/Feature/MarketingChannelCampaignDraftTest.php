<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
class MarketingChannelCampaignDraftTest extends TestCase {
 use RefreshDatabase;
 public function test_channel_draft_is_private_versioned_and_never_sends():void {
  Http::preventStrayRequests();Queue::fake();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
  $d=['channel'=>'email','name'=>'Autumn newsletter','version'=>0,'document'=>['subject'=>'Our latest collection','from_name'=>'Our store']];
  $r=$this->postJson('/admin/marketing/campaigns/channel-drafts',$d)->assertCreated()->assertJsonPath('campaign.channel','email')->assertJsonPath('campaign.status','Draft');$id=$r->json('campaign.id');
  $this->postJson('/admin/marketing/campaigns/channel-drafts/'.$id,$d)->assertStatus(409);
  $this->getJson('/admin/marketing/campaigns/data')->assertJsonPath('campaigns.0.document.subject','Our latest collection');
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->postJson('/admin/marketing/campaigns/channel-drafts/'.$id,array_replace($d,['version'=>1]))->assertNotFound();
  Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_channel_route_does_not_accept_publish_or_unknown_template():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));Http::preventStrayRequests();Queue::fake();
  $this->postJson('/admin/marketing/campaigns/channel-drafts',['channel'=>'whatsapp','name'=>'Campaign','version'=>0,'document'=>['template_id'=>'unowned']])->assertUnprocessable();
  $this->postJson('/admin/marketing/campaigns/channel-drafts',['channel'=>'post','name'=>'Campaign','version'=>0,'document'=>[]])->assertUnprocessable();
  Http::assertNothingSent();Queue::assertNothingPushed();
 }
 public function test_campaign_channels_cannot_mix_posts_and_whatsapp_messages():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));Http::preventStrayRequests();Queue::fake();
  $base=['name'=>'Single channel','objective'=>'Brand awareness','start'=>'2026-10-29','end'=>'2026-10-29','timezone'=>'UTC','destination_url'=>'https://example.com','cta'=>'Learn more','version'=>0];
  $post=$this->postJson('/admin/marketing/campaigns/data',$base)->assertCreated()->json('campaign.id');
  $this->postJson('/admin/marketing/templates/messages',['version'=>0,'campaign_id'=>$post,'template_id'=>(string)\Illuminate\Support\Str::uuid(),'values'=>[],'timezone'=>'UTC'])->assertStatus(422);
  $email=$this->postJson('/admin/marketing/campaigns/channel-drafts',['name'=>'Email only','channel'=>'email','version'=>0,'document'=>[]])->assertCreated()->json('campaign.id');
  $this->postJson('/admin/marketing/campaigns/data/'.$email.'/posts',['version'=>1,'post_ids'=>[]])->assertStatus(422);
  $this->postJson('/admin/marketing/campaigns/channel-drafts/'.$email,['name'=>'Change type','channel'=>'sms','version'=>1,'document'=>[]])->assertStatus(409);
  Http::assertNothingSent();Queue::assertNothingPushed();
 }
}
