<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\{MarketingVideoBoundaryPolicy,MarketingVideoRoutes,MarketingVideoPromptAdaptation,MarketingClipSource};
class MarketingVideoBoundaryPolicyTest extends TestCase {
 private function rhythm():array{return ['speech_start'=>1,'speech_end'=>3,'silent_beats'=>[['start'=>0,'end'=>1,'purpose'=>'建立动作'],['start'=>3,'end'=>9,'purpose'=>'观察动作结果']],'reason'=>'场景故事节奏'];}
 public function test_catalog_has_three_shared_layers_for_all_six_forms_and_verified_routes():void{
  $p=app(MarketingVideoBoundaryPolicy::class);$c=$p->catalog();$this->assertCount(6,$c['forms']);$this->assertArrayHasKey('exact_content',$c['global']);$this->assertSame((new MarketingVideoRoutes)->catalog(),$c['routes']);
  foreach(array_keys($c['forms']) as $form){$i=$p->instruction($form);$this->assertStringContainsString($form,$i);$this->assertStringContainsString($c['version'],$i);}
 }
 public function test_assessment_keeps_legacy_and_creative_advice_nonblocking_without_mutating_source():void{
  $s=['seconds'=>9,'voiceover'=>'休息一下。'];$before=$s;$r=app(MarketingVideoBoundaryPolicy::class)->assess($s);$this->assertSame([],$r['blockers']);$this->assertContains('silence_unplanned',$r['advisories']);$this->assertFalse($r['quality_verified']);$this->assertSame($before,$s);
 }
 public function test_objective_route_and_explicit_timing_failures_block_without_changing_intent():void{
  $s=['seconds'=>9,'voiceover'=>'你好','material_plan'=>['mode'=>'reference_images','roles'=>['start_frame','extra_image']],'rhythm'=>$this->rhythm()];$cap=(new MarketingVideoRoutes)->catalog()['doubao-seedance-1.0-pro-fast'];$r=app(MarketingVideoBoundaryPolicy::class)->assess($s,$cap,true);$this->assertContains('reference_mode_unsupported',$r['blockers']);$this->assertContains('reference_count_unsupported',$r['blockers']);$this->assertContains('native_audio_unsupported',$r['blockers']);
  $s['rhythm']['speech_end']=12;$this->assertContains('rhythm_invalid',app(MarketingVideoBoundaryPolicy::class)->assess($s)['blockers']);
 }
 public function test_new_clip_transports_rhythm_but_legacy_shape_stays_unchanged():void{
  $s=['readiness'=>'qa_passed','asset'=>['asset_id'=>'image'],'scene_id'=>'s','revision_id'=>'r','ordinal'=>1,'seconds'=>9,'frames'=>[],'director'=>[],'voiceover'=>'你好','subtitle'=>'你好','visual'=>'cup','rhythm'=>$this->rhythm()];$h=['contract'=>'storyboard-production-v1','scene_map'=>[$s],'profile'=>[],'voice_route'=>[]];$source=new MarketingClipSource;$clips=$source->storyboard($h);$this->assertSame($s['rhythm'],$clips[0]['rhythm']);unset($h['scene_map'][0]['rhythm']);$this->assertArrayNotHasKey('rhythm',$source->storyboard($h)[0]);
 }
 public function test_compiler_uses_language_and_speaker_intent_and_keeps_exact_script_timing():void{
  $s=['voiceover'=>'原文不改','presenter_required'=>false,'frames'=>[],'rhythm'=>$this->rhythm()];$payload=['model'=>'seedance-1.5-pro','duration'=>9,'generate_audio'=>true];$p=new MarketingVideoPromptAdaptation;
  $out=$p->compose($s,$payload,'Fixed camera, cup stays on table.', ['language'=>'ms-MY']);$this->assertStringContainsString('Malay',$out['prompt']);$this->assertStringContainsString('off-camera',$out['prompt']);$this->assertStringNotContainsString('synchronized presenter lips',$out['prompt']);$this->assertStringContainsString('原文不改',$out['prompt']);$this->assertStringContainsString('speech_start',$out['prompt']);$this->assertStringContainsString('object count',$out['prompt']);
  $s['presenter_required']=true;$out=$p->compose($s,$payload,'Person speaks.', ['language'=>'English']);$this->assertStringContainsString('English',$out['prompt']);$this->assertStringContainsString('synchronized presenter lips',$out['prompt']);
  $payload['generate_audio']=false;$out=$p->compose($s,$payload,'Person rests.', ['language'=>'English']);$this->assertStringNotContainsString('Speak ONLY',$out['prompt']);$this->assertStringContainsString('No generated speech',$out['prompt']);
 }
 public function test_all_creative_roles_share_contract_and_keep_deliberate_surreal_intent():void{
  foreach(['semantic','image','review'] as $role){$text=\App\Services\Marketing\MarketingStoryboardCreativePolicy::$role();$this->assertStringContainsString(MarketingVideoBoundaryPolicy::VERSION,$text);$this->assertStringContainsString('explicit surreal intent',$text);$this->assertStringContainsString('commercial facts',$text);}
 }
 public function test_input_compiler_preserves_timing_and_evidence_before_any_generation():void{
  \Illuminate\Support\Facades\Http::preventStrayRequests();$scene=['scene_id'=>'one','seconds'=>9,'voiceover'=>'只使用这一句','presenter_required'=>false,'frames'=>['start_frame'=>['readiness'=>'qa_passed','asset'=>['asset_id'=>'fake','sha256'=>str_repeat('a',64)]]],'opening_asset'=>[],'director'=>[],'scene_design'=>[],'visual'=>'cup','rhythm'=>$this->rhythm()];
  $route=['video'=>['model'=>'seedance-1.5-pro'],'capability'=>(new MarketingVideoRoutes)->catalog()['seedance-1.5-pro'],'image_roles'=>['start_frame']];unset($route['capability']['max_image_bytes']);$cfg=['clip_routes'=>['one'=>$route],'audio'=>['route'=>'native','language'=>'ms-MY']];
  $method=new \ReflectionMethod(\App\Services\Marketing\MarketingStoryboardProduction::class,'input');$out=$method->invoke(new \App\Services\Marketing\MarketingStoryboardProduction,$scene,$cfg);
  $this->assertSame($scene['rhythm'],$out['rhythm']);$this->assertFalse($out['boundary_assessment']['quality_verified']);$this->assertStringContainsString('ms-MY',$out['payload']['prompt']);$this->assertStringContainsString('speech_start',$out['payload']['prompt']);$this->assertStringContainsString('off-camera narration',$out['payload']['prompt']);\Illuminate\Support\Facades\Http::assertNothingSent();
 }
 public function test_oversized_execution_stops_instead_of_truncating_or_calling_provider():void{
  \Illuminate\Support\Facades\Http::preventStrayRequests();try{(new MarketingVideoPromptAdaptation)->compose(['frames'=>[],'voiceover'=>'保留原稿'],['duration'=>9,'generate_audio'=>true],str_repeat('long visual direction ',100));$this->fail('must reject rather than truncate');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(409,$e->getStatusCode());}\Illuminate\Support\Facades\Http::assertNothingSent();
 }

 public function test_bounded_compilation_retains_full_source_but_omits_nonexecution_rationale():void{
  $s=['seconds'=>9,'frames'=>[],'voiceover'=>'休息一下。','rhythm'=>$this->rhythm()];$s['rhythm']['reason']=str_repeat('完整理由保留。',60);$before=$s;
  $p=new MarketingVideoPromptAdaptation;$payload=['duration'=>9,'generate_audio'=>true];$budget=$p->directionBudget($s,$payload,['language'=>'zh-MY']);$out=$p->compose($s,$payload,str_repeat('x',$budget),['language'=>'zh-MY']);
  $this->assertLessThanOrEqual(2000,strlen($out['prompt']));$this->assertStringContainsString('观察动作结果',$out['prompt']);$this->assertStringNotContainsString('完整理由保留',$out['prompt']);$this->assertSame($before,$s);
 }

 public function test_required_reference_images_are_not_described_as_only_one_opening_frame():void{
  $s=['seconds'=>9,'frames'=>['start_frame'=>['required'=>true],'extra_image'=>['required'=>true]],'voiceover'=>''];
  $out=(new MarketingVideoPromptAdaptation)->compose($s,['duration'=>9,'generate_audio'=>false],'Preserve the supplied scene and reference identity.');$this->assertStringContainsString('identity/action references',$out['prompt']);
 }

}
