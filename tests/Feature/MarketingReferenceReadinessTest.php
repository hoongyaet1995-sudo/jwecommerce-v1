<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingReferenceReadiness;
use Illuminate\Support\Facades\Http;

class MarketingReferenceReadinessTest extends TestCase
{
 public function test_transcripts_and_self_asserted_playback_never_pass_visual_quality_gate():void
 {
  Http::preventStrayRequests();
  $source=['id'=>'saved','video_identity'=>'youtube:examplevid1','observation_level'=>'publisher_transcript_unverified_sync','bounded_excerpt'=>'Confirmed spoken words','quality'=>['playback_observed'=>true,'performance_verified'=>true]];
  $result=app(MarketingReferenceReadiness::class)->assess([$source]);
  $this->assertSame('pending_visual_evidence',$result['status']);
  $this->assertSame(1,$result['usable_text_references']);
  $this->assertSame(0,$result['verified_visual_references']);
  $this->assertFalse($result['marketing_quality_accepted']);
  $this->assertFalse($result['execution_authorized']);
  Http::assertNothingSent();
 }
 public function test_descriptions_or_missing_video_identity_do_not_count_as_usable_transcripts():void
 {
  $result=app(MarketingReferenceReadiness::class)->assess([['id'=>'a','video_identity'=>'youtube:examplevid1','observation_level'=>'page_description','bounded_excerpt'=>'A description'],['id'=>'b','observation_level'=>'publisher_transcript_unverified_sync','bounded_excerpt'=>'words']]);
  $this->assertSame('insufficient_text_evidence',$result['status']);
  $this->assertSame(0,$result['usable_text_references']);
 }
}
