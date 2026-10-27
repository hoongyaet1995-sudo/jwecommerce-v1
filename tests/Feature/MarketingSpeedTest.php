<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingSpeed;
class MarketingSpeedTest extends TestCase {
 private function plan():array{return ['scene_number'=>2,'start_frame'=>24,'end_frame'=>72,'rate'=>2,'protect'=>false];}
 public function test_mapping_and_canonical_duration():void{$s=app(MarketingSpeed::class);$rows=[['number'=>1,'frames'=>87],['number'=>2,'frames'=>112]];$p=$s->normalize($this->plan(),$rows);$this->assertSame(175,$s->frames($rows,$p));$this->assertSame(48,$s->map(72,$p));$this->assertSame(88,$s->map(112,$p));}
 public function test_unknown_scene_and_unknown_properties_rejected():void{$p=$this->plan();$p['command']='bad';$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingSpeed::class)->normalize($p,[['number'=>2,'frames'=>112]]);}
 public function test_speech_protection_blocks_covered_words():void{$p=$this->plan();$p['protect']=true;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingSpeed::class)->normalize($p,[['number'=>2,'frames'=>112,'spoken_text'=>'hello','words'=>[['text'=>'hello','start_frame'=>20,'end_frame'=>80]]]]);}
 public function test_unknown_alignment_is_not_silence():void{$p=$this->plan();$p['protect']=true;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingSpeed::class)->normalize($p,[['number'=>2,'frames'=>112,'spoken_text'=>'hello']]);}
 public function test_slowdown_and_empty_reset():void{$s=app(MarketingSpeed::class);$p=$this->plan();$p['rate']=0.5;$p=$s->normalize($p,[['number'=>2,'frames'=>112]]);$this->assertSame(160,$s->frames([['number'=>2,'frames'=>112]],$p));$this->assertNull($s->normalize(null,[]));}
}
