<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\{User,MarketingPost};
use Illuminate\Support\Str;
class MarketingCampaignTest extends TestCase {
 use RefreshDatabase;
 private function actor(){Http::preventStrayRequests();config(['marketing.execution_enabled'=>false]);return $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));}
 private function data(){return ['name'=>'Collection launch','objective'=>'Product introduction','start'=>'2026-11-01','end'=>'2026-11-15','timezone'=>'Asia/Kuala_Lumpur','destination_url'=>'https://example.com/collection','cta'=>'Learn more','version'=>0];}
 public function test_crud_is_private_versioned_and_does_not_send(){ $this->actor();$r=$this->postJson('/admin/marketing/campaigns/data',$this->data())->assertCreated()->assertJsonPath('campaign.status','Draft');$id=$r->json('campaign.id');$this->getJson('/admin/marketing/campaigns/data')->assertJsonCount(1,'campaigns');$this->patchJson('/admin/marketing/campaigns/data/'.$id,array_replace($this->data(),['version'=>1,'name'=>'Updated']))->assertOk()->assertJsonPath('campaign.version',2);$this->patchJson('/admin/marketing/campaigns/data/'.$id,array_replace($this->data(),['version'=>1]))->assertStatus(409);$this->actor();$this->getJson('/admin/marketing/campaigns/data')->assertJsonCount(0,'campaigns');$this->patchJson('/admin/marketing/campaigns/data/'.$id,array_replace($this->data(),['version'=>2]))->assertNotFound();Http::assertNothingSent(); }
 public function test_only_owned_unlocked_draft_posts_can_be_assigned(){ $this->actor();$u=auth()->user();$id=$this->postJson('/admin/marketing/campaigns/data',$this->data())->json('campaign.id');$p=MarketingPost::create(['id'=>(string)Str::uuid(),'merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'user_id'=>$u->id,'version'=>1,'status'=>'Draft','timezone'=>'UTC','document'=>['title'=>'Draft','targets'=>[]]]);$this->postJson('/admin/marketing/campaigns/data/'.$id.'/posts',['version'=>1,'post_ids'=>[$p->id]])->assertOk()->assertJsonPath('campaign.posts',1);$this->assertDatabaseHas('marketing_posts',['id'=>$p->id,'campaign_id'=>$id,'version'=>2]);$p->update(['status'=>'Published']);$this->postJson('/admin/marketing/campaigns/data/'.$id.'/posts',['version'=>2,'post_ids'=>[]])->assertStatus(409);Http::assertNothingSent(); }
 public function test_bad_dates_and_unsafe_url_are_rejected(){ $this->actor();$this->postJson('/admin/marketing/campaigns/data',array_replace($this->data(),['end'=>'2026-10-01']))->assertUnprocessable();$this->postJson('/admin/marketing/campaigns/data',array_replace($this->data(),['destination_url'=>'javascript:alert(1)']))->assertUnprocessable(); }
 public function test_view_permission_does_not_grant_campaign_edit_or_reports(){ Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$this->actingAs($u);$this->postJson('/admin/marketing/campaigns/data',$this->data())->assertForbidden();$this->getJson('/admin/marketing/analytics/data')->assertForbidden(); }
 public function test_formal_pages_render_without_sample_data(){ $this->actor();$this->get('/admin/marketing/campaigns')->assertOk()->assertSee('campaigns-boot')->assertDontSee('Everyday essentials launch');$this->get('/admin/marketing/analytics')->assertOk()->assertSee('campaigns-boot');Http::assertNothingSent(); }
 public function test_post_campaign_rejects_multiple_posts_without_changing_existing_choice(){
  $this->actor();$id=$this->postJson('/admin/marketing/campaigns/data',$this->data())->json('campaign.id');$ids=[];
  foreach(['First','Second'] as $title){$p=MarketingPost::create(['id'=>(string)Str::uuid(),'merchant_key'=>app(\App\Services\Marketing\MarketingPosts::class)->merchant(),'user_id'=>auth()->id(),'version'=>1,'status'=>'Draft','timezone'=>'UTC','document'=>['title'=>$title,'targets'=>[]]]);$ids[]=$p->id;}
  $this->postJson('/admin/marketing/campaigns/data/'.$id.'/posts',['version'=>1,'post_ids'=>[$ids[0]]])->assertOk();
  $this->postJson('/admin/marketing/campaigns/data/'.$id.'/posts',['version'=>2,'post_ids'=>$ids])->assertUnprocessable();
  $this->assertDatabaseHas('marketing_posts',['id'=>$ids[0],'campaign_id'=>$id]);$this->assertDatabaseHas('marketing_posts',['id'=>$ids[1],'campaign_id'=>null]);Http::assertNothingSent();
 }
 public function test_campaign_post_can_be_saved_without_schedule_and_retains_assignment(){
  $this->actor();$campaign=$this->postJson('/admin/marketing/campaigns/data',$this->data())->json('campaign.id');
  $post=['id'=>(string)Str::uuid(),'version'=>0,'title'=>'Keep for campaign','copy'=>'Draft only','targets'=>[],'copies'=>[],'asset'=>null,'type'=>'Post','tz'=>'UTC'];
  $this->postJson('/admin/marketing/posts/data/draft',$post)->assertOk();
  $this->postJson('/admin/marketing/campaigns/data/'.$campaign.'/posts',['version'=>1,'post_ids'=>[$post['id']]])->assertOk();
  $post['version']=2;$post['copy']='Updated before confirming any schedule';
  $this->postJson('/admin/marketing/posts/data/draft',$post)->assertOk()->assertJsonPath('post.campaign_id',$campaign)->assertJsonPath('post.status','Draft')->assertJsonPath('post.due',null)->assertJsonPath('post.version',3);
  $this->postJson('/admin/marketing/posts/data/draft',$post)->assertStatus(409);
  $this->assertDatabaseHas('marketing_posts',['id'=>$post['id'],'campaign_id'=>$campaign,'status'=>'Draft','version'=>3,'scheduled_at'=>null]);
  Http::assertNothingSent();
 }
}
