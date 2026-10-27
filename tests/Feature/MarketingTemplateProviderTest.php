<?php
namespace Tests\Feature;
use App\Models\AiConnection;
use App\Services\Marketing\MarketingTemplateProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class MarketingTemplateProviderTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();Http::preventStrayRequests();AiConnection::create(['provider'=>'openai','api_key'=>'test-secret','enabled'=>true]);config(['marketing.production.elevenlabs_key'=>'test-voice-secret']);}
 public function test_llm_counts_before_fixed_model_request_and_calculates_cached_usage():void{
  Http::fake(['*/responses/input_tokens'=>Http::response(['input_tokens'=>100],200),'*/responses'=>Http::response(['status'=>'completed','output'=>[],'usage'=>['input_tokens'=>100,'input_tokens_details'=>['cached_tokens'=>20],'output_tokens'=>10]],200,['x-request-id'=>'req-safe'])]);
  $r=app(MarketingTemplateProvider::class)->llm(['input'=>'test'],'client-one');$this->assertSame(14,$r['actual_microusd']);$this->assertSame('req-safe',$r['server_request_id']);
  Http::assertSentCount(2);Http::assertSent(fn($r)=>str_ends_with($r->url(),'/input_tokens')&&!isset($r['store']));Http::assertSent(fn($r)=>str_ends_with($r->url(),'/responses')&&$r['model']==='gpt-6-luna'&&$r['reasoning']['effort']==='medium'&&$r['max_output_tokens']===5000&&$r['store']===false&&$r->header('X-Client-Request-Id')===['client-one']);
 }
 public function test_token_limit_prevents_paid_post():void{
  Http::fake(['*/responses/input_tokens'=>Http::response(['input_tokens'=>12001],200)]);
  try{app(MarketingTemplateProvider::class)->llm(['input'=>'oversize'],'client-two');$this->fail('Expected limit rejection');}catch(\RuntimeException $e){$this->assertSame('template_input_token_limit',$e->getMessage());}Http::assertSentCount(1);
 }
 public function test_missing_usage_keeps_outcome_cost_unknown():void{
  Http::fake(['*/responses/input_tokens'=>Http::response(['input_tokens'=>1],200),'*/responses'=>Http::response(['status'=>'completed','output'=>[]],200)]);
  $this->assertArrayNotHasKey('actual_microusd',app(MarketingTemplateProvider::class)->llm(['input'=>'x'],'three'));
 }
 public function test_tts_uses_approved_voice_and_character_cost_header():void{
  Http::fake(['api.elevenlabs.io/*'=>Http::response(['audio_base64'=>base64_encode(str_repeat('a',30)),'alignment'=>['characters'=>['x']]],200,['character-cost'=>'2','request-id'=>'voice-safe'])]);
  $r=app(MarketingTemplateProvider::class)->tts(['voice_id'=>'hpp4J3VqNfWAUOO0d1Us','text'=>'x'],'voice-client');$this->assertSame(160,$r['actual_microusd']);$this->assertSame('voice-safe',$r['server_request_id']);$this->assertArrayHasKey('alignment',$r['data']);
  Http::assertSent(fn($r)=>$r['model_id']==='eleven_multilingual_v2'&&str_contains($r->url(),'with-timestamps'));Http::assertSentCount(1);
 }
 public function test_tts_missing_cost_header_is_unknown_and_unapproved_voice_never_calls():void{
  Http::fake(['api.elevenlabs.io/*'=>Http::response(['audio_base64'=>base64_encode(str_repeat('a',30)),'alignment'=>[]],200)]);
  $this->assertArrayNotHasKey('actual_microusd',app(MarketingTemplateProvider::class)->tts(['voice_id'=>'ByhETIclHirOlWnWKhHc','text'=>'tea'],'voice-client'));
  try{app(MarketingTemplateProvider::class)->tts(['voice_id'=>'arbitrary','text'=>'x'],'bad');$this->fail('Expected voice rejection');}catch(\RuntimeException $e){$this->assertSame('template_voice_not_approved',$e->getMessage());}Http::assertSentCount(1);
 }
 public function test_tts_rejects_long_text_before_transport():void{
  Http::fake();try{app(MarketingTemplateProvider::class)->tts(['voice_id'=>'ByhETIclHirOlWnWKhHc','text'=>str_repeat('a',301)],'long');$this->fail('Expected text limit');}catch(\RuntimeException $e){$this->assertSame('template_voice_text_limit',$e->getMessage());}Http::assertNothingSent();
 }
 public function test_http_failure_is_never_retried():void{
  Http::fake(['api.elevenlabs.io/*'=>Http::response([],503)]);
  try{app(MarketingTemplateProvider::class)->tts(['voice_id'=>'ByhETIclHirOlWnWKhHc','text'=>'x'],'failure');$this->fail('Expected HTTP failure');}catch(\Illuminate\Http\Client\RequestException $e){$this->assertSame(503,$e->response->status());}Http::assertSentCount(1);
 }
}
