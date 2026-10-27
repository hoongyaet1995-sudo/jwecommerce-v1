<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Marketing\{MarketingSemanticCompiler,MarketingSemanticFacts,MarketingSemanticProof,MarketingInformationCards,MarketingSubtitleService};
class MarketingSemanticCompilerTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 private function source():array {[$p,$f]=$this->editingFilm();$p->update(['brief'=>array_replace($p->brief,['source_confirmed'=>true,'facts_confirmed'=>true,'facts'=>'适合设备存放。'])]);$p->currentRevision->update(['context'=>array_replace($p->currentRevision->context,['brief'=>$p->brief])]);return [$p,$f];}
 public function test_unknown_fact_is_skipped():void {[$p,$f]=$this->source();$facts=app(MarketingSemanticFacts::class)->catalogue($f);$out=app(MarketingSemanticCompiler::class)->compile($f,['facts'=>$facts,'samples'=>[],'references'=>[]],['scenes'=>[['scene_number'=>1,'confidence'=>'high','fact_ids'=>['invented']]]]);$this->assertEmpty($out['applied']);$this->assertNotEmpty($out['skipped']);}
 public function test_position_accounts_for_zoomed_people_and_target_regions():void {
  $service=app(MarketingSemanticCompiler::class);$method=new \ReflectionMethod($service,'position');
  $frame=['mode'=>'fixed','zoom'=>1.3,'x'=>50,'y'=>50,'start_frame'=>0,'end_frame'=>96];
  $this->assertNull($method->invoke($service,'top-left',0,96,null,[[0,.23,1,.12]],['title'=>'产品用途','body'=>'已确认用途'],$frame,96,[]));
 }
 public function test_valid_card_is_exact_fact_and_signed_metadata_rejects_tampering():void {
  [$p,$f]=$this->source();$facts=app(MarketingSemanticFacts::class)->catalogue($f);$atom=collect($facts['atoms'])->firstWhere('text','适合设备存放。');$rows=app(MarketingSubtitleService::class)->scenes($f);
  $samples=array_map(fn($i)=>['id'=>'sample-'.$i,'scene_number'=>1],range(1,3));$layout=array_map(fn($s)=>['sample_id'=>$s['id'],'confidence'=>'high','people'=>[]],$samples);
  $decision=['scene_number'=>1,'confidence'=>'high','fact_ids'=>[$atom['id']],'reason'=>'confirmed','target'=>null,'layout_observations'=>$layout,'card'=>['title_key'=>'purpose','body_fact_id'=>$atom['id'],'start_frame'=>0,'end_frame'=>48,'position'=>'top-left']];
  $out=app(MarketingSemanticCompiler::class)->compile($f,['facts'=>$facts,'samples'=>$samples,'references'=>[]],['scenes'=>[$decision]]);$card=$out['state']['information_cards'][0];$this->assertSame($atom['text'],$card['body']);$this->assertTrue(MarketingSemanticProof::valid($card['semantic']));
  $card['body']='虚构用途';$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingInformationCards::class)->normalize([$card],$rows);
 }
}
