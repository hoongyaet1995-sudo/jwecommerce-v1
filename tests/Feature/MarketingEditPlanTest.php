<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http,Storage};
use Illuminate\Support\Str;
use App\Services\Marketing\MarketingEditPlanService;
use App\Jobs\ProcessMarketingWorkspaceVideo;
class MarketingEditPlanTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_repeated_adoption_reuses_one_local_film_and_preserves_original():void {
  [$p,$base,$clips]=$this->editingFilm();$service=app(MarketingEditPlanService::class);$plan=$service->propose($p,$base,$this->editingCandidate($clips),$this->editingAnalyses($clips),$this->editingMessage($p)->id);$request=(string)Str::uuid();
  $first=$service->adopt($p->id,$p->owner_id,$plan->id,$request,$base->id,$base->output_sha256);$again=$service->adopt($p->id,$p->owner_id,$plan->id,$request,$base->id,$base->output_sha256);
  $this->assertSame($first->id,$again->id);Queue::assertPushed(ProcessMarketingWorkspaceVideo::class,1);$this->assertNull($first->approved_at);$this->assertNotSame($base->fingerprint,$first->fingerprint);$this->assertSame(108,$first->input['total_frames']);$this->assertSame(15,$base->fresh()->input['duration']);$this->assertSame('completed',$base->fresh()->status);$this->assertDatabaseCount('marketing_video_films',2);Http::assertNothingSent();
 }
 public function test_invalid_or_unsafe_candidates_cannot_create_plans():void {
  [$p,$base,$clips]=$this->editingFilm();$service=app(MarketingEditPlanService::class);$e=$this->editingAnalyses($clips);$good=$this->editingCandidate($clips);$cases=[];
  foreach([['in_frame',-1],['in_frame',10],['out_frame',$clips[0]->input['seconds']*24+1],['out_frame',60],['out_frame',1.5],['clip_id',(string)Str::uuid()]] as [$key,$value]){$c=$good;$c['scenes'][0][$key]=$value;$cases[]=$c;}
  $c=$good;array_pop($c['scenes']);$cases[]=$c;$c=$good;$c['scenes'][]=$c['scenes'][0];$cases[]=$c;$c=$good;$c['url']='https://bad.example';$cases[]=$c;$c=$good;$c['duration_policy']='exact';$c['target_frames']=360;$cases[]=$c;
  foreach($cases as $candidate){try{$service->propose($p,$base,$candidate,$e,$this->editingMessage($p)->id);$this->fail('Unsafe edit accepted');}catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){$this->assertTrue(true);}}
  $this->assertDatabaseCount('marketing_edit_plans',0);Queue::assertNothingPushed();Http::assertNothingSent();
 }
 public function test_revoked_reference_or_tampered_evidence_blocks_adoption():void {
  [$p,$base,$clips]=$this->editingFilm();$service=app(MarketingEditPlanService::class);$e=$this->editingAnalyses($clips);$plan=$service->propose($p,$base,$this->editingCandidate($clips),$e,$this->editingMessage($p)->id);
  $p->assets()->whereKey($clips[0]->input['image']['asset_id'])->update(['authorized'=>false]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$service->adopt($p->id,$p->owner_id,$plan->id,(string)Str::uuid(),$base->id,$base->output_sha256);
 }
 public function test_cta_hold_and_silent_visual_protection_are_enforced():void {
  [$p,$base,$clips]=$this->editingFilm();$service=app(MarketingEditPlanService::class);$e=$this->editingAnalyses($clips);$candidate=$this->editingCandidate($clips);$candidate['cta_hold_frames']=60;
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$service->propose($p,$base,$candidate,$e,$this->editingMessage($p)->id);
 }
 public function test_silent_product_action_cannot_be_cut_merely_because_audio_is_silent():void {
  [$p,$base,$clips]=$this->editingFilm();$e=$this->editingAnalyses($clips);$data=$e[0]->evidence;unset($data['digest']);$data['protected_intervals']=[['in_frame'=>0,'out_frame'=>$clips[0]->input['seconds']*24,'reason'=>'required product demonstration']];$data['digest']=\App\Services\Marketing\MarketingEditingDigest::hash($data);$e[0]->update(['evidence'=>$data]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEditPlanService::class)->propose($p,$base,$this->editingCandidate($clips),$e,$this->editingMessage($p)->id);
 }
 public function test_missing_asr_final_clause_cannot_be_cut_past_independent_activity():void {
  [$p,$base,$clips]=$this->editingFilm();$e=$this->editingAnalyses($clips);$data=$e[0]->evidence;unset($data['digest']);$data['silence_intervals']=[['kind'=>'start','frame'=>300],['kind'=>'end','frame'=>360]];$data['digest']=\App\Services\Marketing\MarketingEditingDigest::hash($data);$e[0]->update(['evidence'=>$data]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingEditPlanService::class)->propose($p,$base,$this->editingCandidate($clips),$e,$this->editingMessage($p)->id);
 }
 public function test_head_requires_explicit_preference_and_current_visual_evidence():void {
  [$p,$base,$clips]=$this->editingFilm();$e=$this->editingAnalyses($clips);$data=$e[0]->evidence;unset($data['digest']);$data['can_trim_head']=true;$data['protected_intervals']=[['in_frame'=>24,'out_frame'=>72,'reason'=>'required speech/action']];$data['digest']=\App\Services\Marketing\MarketingEditingDigest::hash($data);$e[0]->update(['evidence'=>$data]);$c=$this->editingCandidate($clips);$c['scenes'][0]['in_frame']=12;$plan=app(MarketingEditPlanService::class)->propose($p,$base,$c,$e,$this->editingMessage($p,['head_trim_requested'=>true])->id);$this->assertSame(96,$plan->recipe['total_frames']);
 }
 public function test_modified_evidence_is_rejected_even_with_unchanged_source_clip():void {
  [$p,$base,$clips]=$this->editingFilm();$e=$this->editingAnalyses($clips);$s=app(MarketingEditPlanService::class);$plan=$s->propose($p,$base,$this->editingCandidate($clips),$e,$this->editingMessage($p)->id);$data=$e[0]->evidence;$data['speech_end_frame']=1;$e[0]->update(['evidence'=>$data]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$s->adopt($p->id,$p->owner_id,$plan->id,(string)Str::uuid(),$base->id,$base->output_sha256);
 }
}
