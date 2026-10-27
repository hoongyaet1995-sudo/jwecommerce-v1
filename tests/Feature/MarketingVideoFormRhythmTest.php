<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\{MarketingVideoFormPolicy,MarketingSpeechRhythm,MarketingStoryboardTiming,MarketingStoryboardContract};
class MarketingVideoFormRhythmTest extends TestCase {
 public function test_six_forms_have_distinct_boundaries_and_auto_keeps_all_options():void {
  $p=new MarketingVideoFormPolicy;$all=$p->catalog();$this->assertCount(6,$all);$this->assertCount(6,array_unique(array_column($all,'direction')));
  $this->assertStringContainsString('situation',$p->instruction('scenario_story'));$this->assertStringContainsString('observable',$p->instruction('feature_demo'));$this->assertStringContainsString('presenter',$p->instruction('auto'));
 }
 public function test_rhythm_requires_complete_nonoverlapping_time_and_preserves_silent_intent():void {
  $r=new MarketingSpeechRhythm;$s=['seconds'=>9,'voiceover'=>'认识 Jasmine Green Tea。了解产品。'];
  $a=$r->analyze($s);$this->assertGreaterThan(0,$a['estimated_speech_seconds']);$this->assertContains('silence_unplanned',$a['warnings']);
  $s['rhythm']=['speech_start'=>0,'speech_end'=>4,'silent_beats'=>[['start'=>4,'end'=>9,'purpose'=>'端杯后停留，留出品牌阅读时间']],'reason'=>'收尾留白'];$r->validate($s);$this->assertNotContains('silence_unplanned',$r->analyze($s)['warnings']);
  $s['rhythm']['silent_beats'][0]['start']=3;$this->expectException(\Illuminate\Validation\ValidationException::class);$r->validate($s);
 }
 public function test_legacy_timing_gives_advice_without_changing_readiness_or_original_text():void {
  $s=['scene_id'=>'one','seconds'=>9,'voiceover'=>'认识 Jasmine Green Tea。了解产品。'];$before=$s;$t=(new MarketingStoryboardTiming)->calculate([$s]);$this->assertFalse($t['requires_attention']);$this->assertContains('silence_unplanned',$t['speech_rhythm'][0]['warnings']);$this->assertSame($before,$s);
 }
 public function test_authoring_retains_valid_rhythm_without_baking_it_into_image_signature():void {
  $c=['seconds'=>9,'message'=>'Tea','visual'=>'Tea cup','voiceover'=>'休息一下。','subtitle'=>'Tea','rhythm'=>['speech_start'=>0,'speech_end'=>2,'silent_beats'=>[['start'=>2,'end'=>9,'purpose'=>'观察泡茶动作']],'reason'=>'动作主导']];
  $contract=new MarketingStoryboardContract;$out=$contract->content($c);$this->assertSame($c['rhythm'],$out['rhythm']);$sig=$contract->signature($out);$out['rhythm']['reason']='从容收尾';$this->assertSame($sig,$contract->signature($out));
 }
 public function test_initial_and_adjustment_schemas_request_rhythm_and_writer_preserves_then_validates_it():void {
  $initial=(new \App\Services\Marketing\MarketingDraftContract)->storyboardSchema();$adjust=(new \App\Services\Marketing\MarketingStoryboardOpenAi)->schema();
  $this->assertContains('rhythm',$initial['properties']['scenes']['items']['required']);$this->assertContains('rhythm',$adjust['properties']['scenes']['items']['required']);
  $scene=['visual'=>'Tea cup','voiceover'=>'休息一下。','subtitle'=>'Tea','duration'=>9,'material'=>'Planned scene','asset_ids'=>[],'rhythm'=>['speech_start'=>0,'speech_end'=>2,'silent_beats'=>[['start'=>2,'end'=>9,'purpose'=>'观察泡茶动作']],'reason'=>'动作主导']];
  $data=['summary'=>'Tea','duration'=>9,'cta'=>'了解产品','changes'=>[],'gaps'=>[],'scenes'=>[$scene]];$facts=['brief'=>['cta'=>'了解产品'],'assets'=>[]];
  $writer=new \App\Services\Marketing\MarketingWriter;$out=$writer->validateOutput($data,'storyboard',$facts);$this->assertSame($scene['rhythm'],$out['scenes'][0]['rhythm']);
  $data['scenes'][0]['rhythm']['silent_beats'][0]['end']=8;$this->expectException(\Illuminate\Validation\ValidationException::class);$writer->validateOutput($data,'storyboard',$facts);
 }
}
