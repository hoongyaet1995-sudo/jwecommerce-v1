<?php
namespace Tests\Feature;
use Tests\TestCase;use App\Services\Marketing\MarketingSemanticEvidence;
class MarketingSemanticEvidenceTest extends TestCase {
 public function test_sampling_is_bounded_and_maps_trimmed_source_frames():void {$s=app(MarketingSemanticEvidence::class);$rows=[['number'=>1,'frames'=>87,'in_frame'=>20],['number'=>2,'frames'=>112,'in_frame'=>8]];$points=$s->points($rows);$this->assertLessThanOrEqual(18,count($points));foreach($points as $p){$r=$rows[$p['scene_number']-1];$this->assertSame($p['frame']+$r['in_frame'],$p['raw_frame']);$this->assertLessThan($r['frames'],$p['frame']);}$this->assertLessThanOrEqual(36,count($s->points(array_map(fn($n)=>['number'=>$n,'frames'=>120,'in_frame'=>0],range(1,12)))));}
 public function test_too_many_scenes_or_short_scene_are_rejected():void {$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);app(MarketingSemanticEvidence::class)->points(array_map(fn($n)=>['number'=>$n,'frames'=>120,'in_frame'=>0],range(1,13)));}
}
