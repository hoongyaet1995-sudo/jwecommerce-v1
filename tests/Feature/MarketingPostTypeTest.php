<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{User,MarketingPost,MarketingDailyDraftRun};
use App\Services\Marketing\MarketingPosts;
class MarketingPostTypeTest extends TestCase {
 use RefreshDatabase;
 private function makePost(User $u,array $context=[],string $format='image'):MarketingPost {return MarketingPost::create(['id'=>(string)Str::uuid(),'user_id'=>$u->id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'version'=>1,'status'=>'Draft','timezone'=>'Asia/Kuala_Lumpur','document'=>['title'=>'Test','copy'=>'Text','type'=>$format==='video'?'Reel':'Post','format'=>$format,'assets'=>[],'targets'=>[],'ai_context'=>$context]]);}
 public function test_historical_generated_type_is_read_from_owned_frozen_run_without_changing_draft():void {
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$id=(string)Str::uuid();MarketingDailyDraftRun::create(['id'=>$id,'user_id'=>$u->id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'status'=>'completed','snapshot'=>['facts'=>['type'=>'storytelling']],'post_ids'=>[]]);$p=$this->makePost($u,['daily_draft_run'=>$id]);
  $this->assertSame('storytelling',app(MarketingPosts::class)->listing($u->id)[0]['post_type']);$this->assertSame(1,$p->fresh()->version);$this->assertArrayNotHasKey('post_type',$p->fresh()->document);
 }
 public function test_foreign_or_missing_generated_metadata_is_not_guessed():void {
  $u=User::factory()->create();$other=User::factory()->create();$id=(string)Str::uuid();MarketingDailyDraftRun::create(['id'=>$id,'user_id'=>$other->id,'merchant_key'=>app(MarketingPosts::class)->merchant(),'status'=>'completed','snapshot'=>['facts'=>['type'=>'promotion']],'post_ids'=>[]]);$p=$this->makePost($u,['daily_draft_run'=>$id]);$this->assertSame('unspecified',app(MarketingPosts::class)->view($p)['post_type']);
 }
 public function test_manual_formats_have_honest_fallback_labels():void {
  $u=User::factory()->create();$s=app(MarketingPosts::class);
  foreach(['image'=>'image','carousel'=>'multi-image','video'=>'video'] as $format=>$type)$this->assertSame($type,$s->view($this->makePost($u,[],$format))['post_type']);
 }
}
