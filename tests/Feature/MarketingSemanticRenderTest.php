<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\MarketingVideoFilm;
use App\Services\Marketing\{MarketingSemanticGeometry,MarketingSemanticProof,MarketingSemanticCompiler};
class MarketingSemanticRenderTest extends TestCase {
 public function test_rejected_replacement_restores_original_effect_when_card_survives():void {
  \Illuminate\Support\Facades\Storage::fake('local');$old=['scene_number'=>1,'kind'=>'arrow'];$effect=['scene_number'=>1,'kind'=>'circle','grounding'=>['scene_number'=>1]];
  $this->mock(\App\Services\Marketing\MarketingSubtitleService::class)->shouldReceive('scenes')->once()->andReturn([]);$this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldReceive('run')->once()->andReturn('[{"scene_number":1,"intervals":[]}]');
  $c=['state'=>['emphasis'=>[$effect],'framing'=>[],'information_cards'=>[],'overlays'=>[]],'applied'=>[['scene_number'=>1,'kind'=>'information_card'],['scene_number'=>1,'kind'=>'grounded_emphasis']],'skipped'=>[],'fallback_emphasis'=>[$old]];
  $out=app(MarketingSemanticGeometry::class)->audit(new MarketingVideoFilm,$c,'fixture');$this->assertSame([$old],$out['state']['emphasis']);$this->assertCount(1,$out['applied']);
 }
 public function test_empty_change_does_not_invoke_renderer():void {$this->mock(\App\Services\Marketing\MarketingMediaTools::class)->shouldNotReceive('run');$c=['state'=>[],'applied'=>[]];$this->assertSame($c,app(MarketingSemanticGeometry::class)->audit(new MarketingVideoFilm,$c,'fixture'));}
 public function test_geometry_proof_is_invalid_after_trajectory_tampering():void {$p=MarketingSemanticProof::seal(['source_sha256'=>'source','points'=>[['frame'=>0,'bbox'=>[.4,.3,.3,.2]]]]);$this->assertTrue(MarketingSemanticProof::valid($p));$p['points'][0]['bbox'][0]=.8;$this->assertFalse(MarketingSemanticProof::valid($p));}
 public function test_card_protected_box_conservatively_covers_content_box_padding():void {$b=app(MarketingSemanticCompiler::class)->cardBox('top-right',['title'=>'Fixture','body'=>'Approved exact fact']);$this->assertLessThanOrEqual(260/720,$b[0]);$this->assertGreaterThanOrEqual(406/720,$b[2]);}
}
