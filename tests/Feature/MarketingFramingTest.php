<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingFraming;
class MarketingFramingTest extends TestCase {
 private function effect():array{return ['scene_number'=>2,'mode'=>'push','zoom'=>1.18,'x'=>40,'y'=>48,'start_frame'=>12,'end_frame'=>84];}
 public function test_actual_frames_and_empty_reset_are_supported():void{
  $s=app(MarketingFraming::class);$e=$this->effect();$rows=[['number'=>2,'frames'=>112]];
  $this->assertSame([$e],$s->normalize([$e],$rows));$this->assertSame([],$s->normalize([],$rows));
  $e['end_frame']=113;$this->expectException(\Illuminate\Validation\ValidationException::class);$s->normalize([$e],$rows);
 }
 public function test_unknown_scene_and_short_interval_are_rejected():void{
  $e=$this->effect();$e['scene_number']=5;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFraming::class)->normalize([$e],[['number'=>2,'frames'=>112]]);
 }
 public function test_interval_must_leave_readable_time():void{
  $e=$this->effect();$e['end_frame']=47;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFraming::class)->normalize([$e],[['number'=>2,'frames'=>112]]);
 }
 public function test_duplicate_scene_plans_are_rejected():void{
  $e=$this->effect();$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFraming::class)->normalize([$e,$e],[['number'=>2,'frames'=>112]]);
 }
 public function test_zoom_above_approved_limit_is_rejected():void{
  $e=$this->effect();$e['zoom']=1.31;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFraming::class)->normalize([$e],[['number'=>2,'frames'=>112]]);
 }
 public function test_arbitrary_commands_and_unknown_scenes_are_not_capabilities():void{
  $e=$this->effect();$e['mode']='ffmpeg_command';$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingFraming::class)->normalize([$e],[['number'=>5,'frames'=>72]]);
 }
}
