<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
use App\Models\{User,Product,AiConnection,StoreSetting,MarketingPost};
class MarketingInstantPostTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array {
  Http::preventStrayRequests();Storage::fake('local');Storage::fake('public');$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);
  StoreSetting::create(['key'=>'shop_profile','value'=>['data'=>['shopName'=>'Test Company']]]);AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'TEST-ONLY','version'=>1,'models'=>[['id'=>'gpt-5.6-luna','text'=>true],['id'=>'gpt-image-2.5-flare','image'=>true]]]);
  $p=Product::create(['title'=>'Desk Lamp','handle'=>'instant-lamp','status'=>'active','description'=>'White lamp. No stand included.','base_price'=>100]);$im=imagecreatetruecolor(100,100);ob_start();imagepng($im);Storage::disk('public')->put('instant-lamp.png',ob_get_clean());imagedestroy($im);$p->images()->create(['path'=>'instant-lamp.png','position'=>0]);
  $v=['id'=>(string)Str::uuid(),'name'=>'Workspace ideas','sources'=>['products'=>[$p->id],'categories'=>[],'collections'=>[],'promotions'=>[],'pages'=>[]],'source_scope'=>'selected','prompt'=>'Show a useful workspace idea','type'=>'poster','image_mode'=>'single','cta_url'=>'','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a'];return [$u,$p,$v];
 }
 public function test_shared_page_has_steps_one_to_five_without_automation_settings():void {
  $this->fixture();$this->get('/admin/marketing/ai-posts')->assertOk()->assertSee('Post name')->assertSee('Build your resource pool')->assertSee('What kind of post?')->assertSee('Make the image and words work together')->assertSee('GENERATION PREVIEW')->assertDontSee('Generation schedule')->assertDontSee('Automatic Publish Status')->assertDontSee('Save automation')->assertDontSee('name="schedule_enabled"',false);
  $this->assertDatabaseCount('marketing_post_automations',0);Http::assertNothingSent();
 }
 public static function imageModes():array {return [['single',1],['multiple',2]];}
 #[\PHPUnit\Framework\Attributes\DataProvider('imageModes')]
 public function test_instant_source_selection_generates_drafts_without_creating_an_automation(string $mode,int $slides):void {
  [$u,$p,$v]=$this->fixture();$v['image_mode']=$mode;$plan=['angle'=>'A useful desk','headline'=>'Workspace ideas','cta'=>'Explore','variants'=>['facebook'=>'Desk Lamp. No stand included.'],'slides'=>array_map(fn($i)=>['headline'=>'Useful desk '.$i,'prompt'=>'Lamp artwork '.$i],range(1,$slides))];
  $im=imagecreatetruecolor(1024,1280);ob_start();imagepng($im);$png=base64_encode(ob_get_clean());imagedestroy($im);
  Http::fake(['api.openai.com/v1/responses'=>function($request)use($p,$plan){return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(str_contains(json_encode($request->data()),'AUTOMATION_SOURCE_SELECTION')?['product_ids'=>[$p->id],'page_ids'=>[],'promotion_ids'=>[],'reason'=>'Useful workspace']:$plan)]]]]]);},'api.openai.com/v1/images/edits'=>Http::response(['data'=>[['b64_json'=>$png]]])]);
  $this->postJson('/admin/marketing/instant-posts',$v+['schedule_enabled'=>true,'auto_publish'=>true])->assertOk()->assertJsonPath('status','selection_prepared');
  for($i=0;$i<$slides+4;$i++)$result=$this->postJson('/admin/marketing/instant-posts/'.$v['id'].'/step')->assertOk();$result->assertJsonPath('status','completed');
  $this->postJson('/admin/marketing/instant-posts/'.$v['id'].'/step')->assertOk();$this->postJson('/admin/marketing/instant-posts',$v)->assertOk()->assertJsonPath('status','completed');
  $this->assertDatabaseCount('marketing_post_automations',0);$this->assertDatabaseCount('marketing_post_dispatches',0);$this->assertDatabaseCount('marketing_automation_occurrences',0);
  $post=MarketingPost::firstOrFail();$this->assertSame('Draft',$post->status);$this->assertSame('Workspace ideas',$post->document['title']);$this->assertSame([],$post->document['targets']);$this->assertNull($post->document['ai_context']['automation_id']);$this->assertCount($slides,$post->document['assets']);Http::assertSentCount($slides+2);
 }
 public function test_instant_runs_are_private_and_reused_ids_cannot_change_the_brief():void {
  [$u,$p,$v]=$this->fixture();$this->postJson('/admin/marketing/instant-posts',$v)->assertOk();$this->postJson('/admin/marketing/instant-posts',array_replace($v,['prompt'=>'Changed']))->assertConflict();
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->getJson('/admin/marketing/instant-posts/'.$v['id'])->assertNotFound();$this->postJson('/admin/marketing/instant-posts/'.$v['id'].'/step')->assertNotFound();Http::assertNothingSent();
 }
 public function test_unknown_selection_is_retained_without_retry_or_child_generation():void {
  [$u,$p,$v]=$this->fixture();Http::fake(['api.openai.com/*'=>Http::response([],503)]);$this->postJson('/admin/marketing/instant-posts',$v)->assertOk();
  $this->postJson('/admin/marketing/instant-posts/'.$v['id'].'/step')->assertOk()->assertJsonPath('status','selection_unknown');$this->getJson('/admin/marketing/instant-posts/'.$v['id'])->assertOk()->assertJsonPath('can_step',false);$this->postJson('/admin/marketing/instant-posts/'.$v['id'].'/step')->assertOk()->assertJsonPath('status','selection_unknown');
  Http::assertSentCount(1);$this->assertDatabaseCount('marketing_daily_draft_runs',0);$this->assertDatabaseCount('marketing_post_automations',0);
 }

 public function test_explicit_preflight_rejection_reports_uncreated_and_corrected_brief_can_start():void {
  [$u,$p,$v]=$this->fixture();$this->postJson('/admin/marketing/instant-posts',array_replace($v,['prompt'=>'']))->assertUnprocessable()->assertJsonPath('generation_uncreated',true);
  $this->assertDatabaseCount('marketing_instant_post_runs',0);$this->postJson('/admin/marketing/instant-posts',$v)->assertOk()->assertJsonPath('status','selection_prepared');Http::assertNothingSent();
 }

}
