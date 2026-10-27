<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingDynamicFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use App\Services\Marketing\{MarketingEvolinkMedia,MarketingMediaTools,MarketingProductionFailure};
class MarketingGeneratedMediaTest extends TestCase
{
 use RefreshDatabase,MarketingDynamicFixtures;
 private function media(bool $audio=false):array {
  [$p,$r]=$this->dynamicFixture();[$plan,$t]=$this->dynamicStart($p,$r);$tools=$this->mock(MarketingMediaTools::class);$streams=[['codec_type'=>'video','codec_name'=>'h264','width'=>720,'height'=>1280,'nb_read_frames'=>'120']];if($audio)$streams[]=['codec_type'=>'audio','codec_name'=>'aac'];$tools->shouldReceive('probe')->andReturn(['streams'=>$streams,'format'=>['duration'=>5]]);if(!$audio)$tools->shouldReceive('run')->twice()->andReturn('');else $tools->shouldNotReceive('run');
  $service=new class extends MarketingEvolinkMedia {protected function addresses(string $host):array{return ['8.8.8.8'];}};Http::fake(['https://media.example.test/shot.mp4'=>fn()=>Http::response(str_repeat('fixture-media',200))]);return [$t,$service];
 }
 public function test_silent_shot_is_hashed_and_repeated_download_never_overwrites_different_bytes():void {
  [$t,$service]=$this->media();$a=$service->storeShot($t,'https://media.example.test/shot.mp4');$b=$service->storeShot($t,'https://media.example.test/shot.mp4');$this->assertSame($a['sha256'],$b['sha256']);$this->assertFalse($a['has_audio']);$this->assertSame('marketing-productions/'.$t->id.'/generated-shot.mp4',$a['path']);$this->assertFalse(Storage::disk('local')->exists('marketing-productions/'.$t->id.'/output.mp4'));
 }
 public function test_unexpected_audio_is_rejected_before_composition():void {
  [$t,$service]=$this->media(true);$this->expectException(MarketingProductionFailure::class);try{$service->storeShot($t,'https://media.example.test/shot.mp4');}finally{$this->assertFalse(Storage::disk('local')->exists('marketing-productions/'.$t->id.'/generated-shot.mp4'));}
 }
}
