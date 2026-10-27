<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingEmphasis;
class MarketingEmphasisTest extends TestCase {
 private function effect():array{return ['scene_number'=>2,'kind'=>'arrow','x'=>70,'y'=>48,'size'=>30,'angle'=>135,'color'=>'#d2f36b','strength'=>65,'start_frame'=>12,'end_frame'=>100];}
 public function test_actual_scene_parameters_and_reset():void{$e=$this->effect();$s=app(MarketingEmphasis::class);$this->assertSame([$e],$s->normalize([$e],[['number'=>2,'frames'=>112]]));$this->assertSame([],$s->normalize([],[['number'=>2,'frames'=>112]]));}
 public function test_invalid_time_is_rejected():void{$e=$this->effect();$e['end_frame']=113;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingEmphasis::class)->normalize([$e],[['number'=>2,'frames'=>112]]);}
 public function test_unknown_scene_is_rejected():void{$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingEmphasis::class)->normalize([$this->effect()],[['number'=>1,'frames'=>112]]);}
 public function test_commands_are_rejected():void{$e=$this->effect();$e['command']='bad';$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingEmphasis::class)->normalize([$e],[['number'=>2,'frames'=>112]]);}
 public function test_duplicate_effect_is_rejected():void{$e=$this->effect();$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingEmphasis::class)->normalize([$e,$e],[['number'=>2,'frames'=>112]]);}
}
