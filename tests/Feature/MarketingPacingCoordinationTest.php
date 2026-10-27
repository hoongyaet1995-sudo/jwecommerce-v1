<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\{MarketingSpeechRhythm,MarketingVideoFormPolicy,MarketingWriter};
class MarketingPacingCoordinationTest extends TestCase
{
 public function test_fake_draft_short_copy_cannot_hide_inside_long_speech_window():void {
  $scene=['visual'=>'固定茶杯画面','voiceover'=>'休息一下。','subtitle'=>str_repeat('字幕阅读信息',15),'duration'=>10,'material'=>'原图','asset_ids'=>[], 'rhythm'=>['speech_start'=>0,'speech_end'=>10,'silent_beats'=>[],'reason'=>'慢节奏']];
  $data=['summary'=>'Tea','duration'=>10,'cta'=>'了解产品','changes'=>[],'gaps'=>[],'scenes'=>[$scene]];
  $out=(new MarketingWriter)->validateOutput($data,'storyboard',['brief'=>['cta'=>'了解产品'],'assets'=>[]]);
  $warnings=$out['pacing_review']['scenes'][0]['warnings'];
  foreach(['speech_density_low','speech_window_inflated','subtitle_reading_overload'] as $warning)$this->assertContains($warning,$warnings);
  $this->assertSame($scene,$out['scenes'][0]);$this->assertFalse($out['pacing_review']['measured']);
 }
 public function test_purposeful_silent_film_remains_valid_and_generic_long_pause_gets_advice():void {
  $r=new MarketingSpeechRhythm;$s=['seconds'=>10,'voiceover'=>'','subtitle'=>'','rhythm'=>['speech_start'=>0,'speech_end'=>0,'silent_beats'=>[['start'=>0,'end'=>10,'purpose'=>'留白']],'reason'=>'氛围']];
  $r->validate($s);$this->assertContains('silence_purpose_generic',$r->analyze($s)['warnings']);
  $s['rhythm']['silent_beats'][0]['purpose']='观察茶叶从卷曲到展开的连续变化，再读清商品原图名称';
  $this->assertNotContains('silence_purpose_generic',$r->analyze($s)['warnings']);
  $this->assertNotContains('speech_density_low',$r->analyze($s)['warnings']);
 }
 public function test_shared_creative_instruction_coordinates_duration_copy_subtitles_and_form():void {
  $instruction=(new MarketingVideoFormPolicy)->instruction('scenario_story');
  foreach(['PACING COORDINATION','longer video','subtitle','locked','not a mandatory speech quota'] as $part)$this->assertStringContainsString($part,$instruction);
 }
 public function test_director_review_preserves_locked_copy_and_reports_sparse_film():void {
  $facts=MarketingDirectorContractTest::facts();$facts['duration']=30;$facts['scenes'][0]['duration']=30;
  $plan=MarketingDirectorContractTest::directorOutput();$plan['shots'][0]['end']=30;$plan['shots'][0]['beats']=[['start'=>0,'end'=>30,'action'=>'观察茶杯细节']];
  $out=app(\App\Services\Marketing\MarketingDirectorContract::class)->validate($plan,$facts);
  $this->assertContains('film_speech_density_low',$out['pacing_review']['warnings']);
  $this->assertSame($plan['shots'],$out['shots']);$this->assertSame($plan['notes'],$out['notes']);
 }
}
