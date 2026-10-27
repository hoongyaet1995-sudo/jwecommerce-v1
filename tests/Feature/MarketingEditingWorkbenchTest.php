<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\{User,MarketingConversationMessage};
use App\Services\Marketing\MarketingEditPlanService;
class MarketingEditingWorkbenchTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_finished_has_one_config_and_same_thread_inline_drawer_and_selected_film():void {
  [$p,$film,$clips]=$this->editingFilm();$this->actingAs(User::find($p->owner_id));$html=$this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'finished','film'=>$film->id]))->assertOk()->assertSee('AI剪辑助手')->assertSee('能调整什么？')->assertSee('保留完整口播，缩短等待')->getContent();$this->assertSame(1,substr_count($html,'id="ws-chat-config"'));$this->assertSame(2,substr_count($html,'data-project-chat-form'));$this->assertStringContainsString('data-film="'.$film->id.'"',$html);Http::assertNothingSent();
 }
 public function test_real_handoff_arrives_in_modern_step3_without_mutating_project():void {
  [$p,$film]=$this->editingFilm();$this->actingAs(User::find($p->owner_id));$m=$this->editingMessage($p,['handoff'=>['request'=>'把背景换成餐厅','category'=>'background','base_film_id'=>$film->id,'base_sha256'=>$film->output_sha256]]);$version=$p->version;$this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'materials','handoff'=>$m->id]))->assertOk()->assertSee('把背景换成餐厅')->assertSee('本轮已完成真实需求交接');$this->assertSame($version,$p->fresh()->version);Http::assertNothingSent();
 }
 public function test_adopt_route_binds_base_and_is_idempotent_without_approval():void {
  [$p,$film,$clips]=$this->editingFilm();$this->actingAs(User::find($p->owner_id));$s=app(MarketingEditPlanService::class);$plan=$s->propose($p,$film,$this->editingCandidate($clips),$this->editingAnalyses($clips),$this->editingMessage($p)->id);$body=['request_id'=>(string)Str::uuid(),'base_film_id'=>$film->id,'base_sha256'=>$film->output_sha256];$url=route('marketing.workspace.edit-plans.adopt',[$p->id,$plan->id]);$id=$this->postJson($url,$body)->assertOk()->json('film_id');$this->postJson($url,$body)->assertOk()->assertJsonPath('film_id',$id);$this->assertDatabaseCount('marketing_video_films',2);$this->assertDatabaseHas('marketing_video_films',['id'=>$id,'approved_at'=>null]);$this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'finished','film'=>$id]))->assertOk()->assertSee('等待本地合成');Http::assertNothingSent();
 }
 public function test_foreign_owner_cannot_select_film_or_handoff():void {
  [$p,$film]=$this->editingFilm();$other=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($other);$this->get(route('marketing.workspace.show',['project'=>$p->id,'screen'=>'finished','film'=>$film->id]))->assertNotFound();Http::assertNothingSent();
 }
}
