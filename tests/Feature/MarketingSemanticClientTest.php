<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Http;use App\Services\Marketing\MarketingSemanticClient;
class MarketingSemanticClientTest extends TestCase {
 use RefreshDatabase;
 public function test_transport_is_called_once_with_strict_bounded_images_and_no_retry():void {
  \Illuminate\Support\Facades\Storage::fake('local');\App\Models\AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'api_key'=>'fixture-only']);
  $image=\Illuminate\Http\UploadedFile::fake()->image('raw.jpg',72,128);$bytes=file_get_contents($image->getRealPath());\Illuminate\Support\Facades\Storage::disk('local')->put('sample.jpg',$bytes);
  $e=['samples'=>[['id'=>'S0','frame'=>0,'scene_number'=>1,'path'=>'sample.jpg','sha256'=>hash('sha256',$bytes)]],'references'=>[]];
  $this->mock(\App\Services\AI\ProviderTransport::class)->shouldReceive('json')->once()->withArgs(function($method,$url,$headers,$data,$custom,$timeout){$this->assertFalse($data['store']);$this->assertTrue($data['text']['format']['strict']);$this->assertSame(6500,$data['max_output_tokens']);$this->assertFalse($custom);$this->assertSame(180,$timeout);return true;})->andReturn(['status'=>'completed','id'=>'fixture-response','usage'=>['total_tokens'=>1],'output'=>[['content'=>[['type'=>'output_text','text'=>'{"summary":"fixture","scenes":[]}']]]]]);
  $this->assertSame('fixture-response',app(MarketingSemanticClient::class)->inspect($e)['response_id']);Http::assertNothingSent();
 }
 public function test_changed_sample_hash_is_rejected_before_transport():void {
  \Illuminate\Support\Facades\Storage::fake('local');\Illuminate\Support\Facades\Storage::disk('local')->put('sample.jpg','changed');$this->mock(\App\Services\AI\ProviderTransport::class)->shouldNotReceive('json');
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);app(MarketingSemanticClient::class)->inspect(['samples'=>[['id'=>'S0','path'=>'sample.jpg','sha256'=>str_repeat('0',64)]],'references'=>[]]);
 }
 public function test_schema_requires_complete_scene_and_grounding_evidence():void {$s=MarketingSemanticClient::schema();$this->assertFalse($s['additionalProperties']);$scene=$s['properties']['scenes']['items'];$this->assertContains('fact_ids',$scene['required']);$this->assertContains('observations',$scene['properties']['target']['required']);$this->assertContains('identity_match',$scene['properties']['target']['properties']['observations']['items']['required']);Http::assertNothingSent();}
}
