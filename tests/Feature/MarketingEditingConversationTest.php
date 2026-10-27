<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue};
use Illuminate\Support\Str;
use App\Models\MarketingConversationMessage as Message;
use App\Services\Marketing\{MarketingEditingConversation,MarketingTextClient};
use App\Jobs\{ProcessMarketingWorkspaceVideo,ProcessMarketingEditingConversation};
class MarketingEditingConversationTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_overlays_discussion_returns_typed_review_without_rendering_or_new_visual_requests():void {
  [$p,$f]=$this->editingFilm();$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'请给产品图片叠加排版方案，先给方案',$f->id);$m=Message::where('status','pending')->firstOrFail();
  $context=$s->context($p,$f,true,false,false,true);$this->assertTrue($context['packaging']['preserve']);$this->assertStringContainsString('omitted',$context['packaging']['catalogue_scope']);$this->assertLessThan(10000,mb_strlen(json_encode($context,JSON_UNESCAPED_UNICODE)));
  $asset=\App\Models\MarketingAsset::where('project_id',$p->id)->where('mime','image/png')->firstOrFail();$effects=[['id'=>'layer-1','image_id'=>$asset->id,'enabled'=>true,'scene_number'=>1,'x'=>50,'y'=>50,'width'=>20,'radius'=>16,'border'=>3,'color'=>'#ffffff','start_frame'=>12,'end_frame'=>96]];
  $this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'建议轻推近，固定聚焦点需预览确认，未制作。','handoff_category'=>'none','proposal'=>null,'overlays'=>$effects],JSON_UNESCAPED_UNICODE));
  $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');
  $this->mock(\App\Services\Marketing\MarketingClipAnalysis::class)->shouldNotReceive('process');
  $s->process($m->id);$this->assertSame('assistant',$m->fresh()->role);$this->assertSame($asset->id,$m->fresh()->context['overlays_proposal']['settings'][0]['image_id']);$this->assertSame($f->output_sha256,$m->fresh()->context['overlays_proposal']['base_sha256']);
  $this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_emphasis_discussion_returns_typed_review_without_rendering_or_new_visual_requests():void {
  [$p,$f]=$this->editingFilm();$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'请为产品推荐箭头强调，先给方案',$f->id);$m=Message::where('status','pending')->firstOrFail();
  $context=$s->context($p,$f,true,false,true);$this->assertTrue($context['packaging']['preserve']);$this->assertStringContainsString('omitted',$context['packaging']['catalogue_scope']);$this->assertLessThan(10000,mb_strlen(json_encode($context,JSON_UNESCAPED_UNICODE)));
  $effects=[['scene_number'=>1,'kind'=>'arrow','x'=>70,'y'=>48,'size'=>30,'angle'=>135,'color'=>'#d2f36b','strength'=>65,'start_frame'=>12,'end_frame'=>96]];
  $this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'建议轻推近，固定聚焦点需预览确认，未制作。','handoff_category'=>'none','proposal'=>null,'emphasis'=>$effects],JSON_UNESCAPED_UNICODE));
  $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');
  $this->mock(\App\Services\Marketing\MarketingClipAnalysis::class)->shouldNotReceive('process');
  $s->process($m->id);$this->assertSame('assistant',$m->fresh()->role);$this->assertSame($effects,$m->fresh()->context['emphasis_proposal']['settings']);$this->assertSame($f->output_sha256,$m->fresh()->context['emphasis_proposal']['base_sha256']);
  $this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_framing_discussion_returns_typed_review_without_rendering_or_new_visual_requests():void {
  [$p,$f]=$this->editingFilm();$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'请推荐轻微推近构图，先给方案',$f->id);$m=Message::where('status','pending')->firstOrFail();
  $context=$s->context($p,$f,true,true);$this->assertTrue($context['packaging']['preserve']);$this->assertStringContainsString('omitted',$context['packaging']['catalogue_scope']);$this->assertLessThan(10000,mb_strlen(json_encode($context,JSON_UNESCAPED_UNICODE)));
  $effects=[['scene_number'=>1,'mode'=>'push','zoom'=>1.12,'x'=>40,'y'=>50,'start_frame'=>0,'end_frame'=>96]];
  $this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'建议轻推近，固定聚焦点需预览确认，未制作。','handoff_category'=>'none','proposal'=>null,'framing'=>$effects],JSON_UNESCAPED_UNICODE));
  $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');
  $this->mock(\App\Services\Marketing\MarketingClipAnalysis::class)->shouldNotReceive('process');
  $s->process($m->id);$this->assertSame('assistant',$m->fresh()->role);$this->assertSame($effects,$m->fresh()->context['framing_proposal']['settings']);$this->assertSame($f->output_sha256,$m->fresh()->context['framing_proposal']['base_sha256']);
  $this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_subtitle_discussion_returns_reviewable_settings_without_visual_analysis_or_rendering():void {
  [$p,$f]=$this->editingFilm();$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'全部改成逐词高亮字幕，先给方案',$f->id);$m=Message::where('status','pending')->firstOrFail();
  $settings=['mode'=>'highlight','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1];
  $this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'先准备同步，再查看方案；尚未制作。','handoff_category'=>'none','proposal'=>null,'packaging'=>null,'subtitles'=>$settings],JSON_UNESCAPED_UNICODE));
  $this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');
  $s->process($m->id);$this->assertSame('assistant',$m->fresh()->role);$this->assertSame($settings,$m->fresh()->context['subtitle_proposal']['settings']);$this->assertSame($f->id,$m->fresh()->context['subtitle_proposal']['base_film_id']);$this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_discussion_proposes_without_rendering_and_duplicate_send_is_one_job():void {
  [$p,$f,$clips]=$this->editingFilm();$this->editingAnalyses($clips);$s=app(MarketingEditingConversation::class);$request=(string)Str::uuid();$s->enqueue($p,$request,'缩短等待，保留口播',$f->id);$s->enqueue($p,$request,'缩短等待，保留口播',$f->id);Queue::assertPushed(ProcessMarketingEditingConversation::class,1);$m=Message::where('status','pending')->firstOrFail();
  $this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn(json_encode(['reply'=>'建议保留完整台词，缩短等待到4.5秒。','handoff_category'=>'none','proposal'=>$this->editingCandidate($clips)],JSON_UNESCAPED_UNICODE));$this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');$s->process($m->id);$s->process($m->id);$this->assertDatabaseHas('marketing_edit_plans',['message_id'=>$m->id,'status'=>'pending']);$this->assertSame('completed',$m->fresh()->status);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_unsupported_request_produces_real_project_handoff_and_no_generation():void {
  [$p,$f,$clips]=$this->editingFilm();$this->editingAnalyses($clips);$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'把背景换成餐厅',$f->id);$m=Message::where('status','pending')->firstOrFail();$this->mock(MarketingTextClient::class)->shouldReceive('text')->once()->andReturn('{"reply":"需要返回分镜与素材准备新背景，本轮不会重新生成。","handoff_category":"background","proposal":null}');$this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->andReturn('{"tokens":1000,"budget_upper_estimate":2000}');$s->process($m->id);$this->assertSame('把背景换成餐厅',$m->fresh()->context['handoff']['request']);$this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNotPushed(ProcessMarketingWorkspaceVideo::class);Http::assertNothingSent();
 }
 public function test_get_messages_never_calls_ai_and_wait_reconcile_respects_editing_job():void {
  [$p,$f,$clips]=$this->editingFilm();$this->actingAs(\App\Models\User::find($p->owner_id));$s=app(MarketingEditingConversation::class);$s->enqueue($p,(string)Str::uuid(),'可以',$f->id);$this->getJson(route('marketing.conversation.show',$p->id))->assertOk();$this->postJson(route('marketing.conversation.reconcile',$p->id))->assertStatus(409);$this->assertDatabaseCount('marketing_ai_runs',1);Http::assertNothingSent();
 }
 public function test_unknown_analysis_requires_explicit_recovery_preserves_claim_and_queues_once():void {
  [$p,$f,$clips]=$this->editingFilm();$s=app(MarketingEditingConversation::class);$a=app(\App\Services\Marketing\MarketingClipAnalysis::class)->ensure($clips[0]);$run=\App\Models\MarketingAiRun::create(['project_id'=>$p->id,'request_id'=>(string)Str::uuid(),'action'=>'editing_vision','status'=>'running','reserved_usd'=>.02,'usage'=>['analysis_id'=>$a->id]]);$a->update(['status'=>'analyzing','run_id'=>$run->id]);$m=$this->editingMessage($p,['film_id'=>$f->id,'film_sha256'=>$f->output_sha256,'active_analysis_id'=>$a->id,'stage'=>'analysis']);$m->update(['status'=>'pending']);$s->markUnknown($m->id);$this->assertSame('unknown',$a->fresh()->status);$this->assertSame('unknown',$run->fresh()->status);Queue::assertNotPushed(ProcessMarketingEditingConversation::class);
  $this->actingAs(\App\Models\User::find($p->owner_id));$url=route('marketing.workspace.editing.retry',[$p->id,$m->id]);$request=(string)Str::uuid();$this->postJson($url,['request_id'=>$request])->assertUnprocessable();$this->post($url,['request_id'=>$request,'consent'=>1])->assertRedirect();$this->post($url,['request_id'=>$request,'consent'=>1])->assertRedirect();$this->assertSame('queued',$a->fresh()->status);$this->assertNull($a->fresh()->run_id);$this->assertSame($run->id,$a->fresh()->usage['attempts'][0]['run_id']);Queue::assertPushed(ProcessMarketingEditingConversation::class,1);Http::assertNothingSent();
 }
 public function test_bounded_legacy_grants_cannot_be_used_for_editing_paid_calls():void {
  [$p,$f]=$this->editingFilm();config(['marketing.conversation.open_testing'=>false]);$this->assertFalse(MarketingEditingConversation::enabled());$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEditingConversation::class)->enqueue($p,(string)Str::uuid(),'缩短等待',$f->id);
 }
}
