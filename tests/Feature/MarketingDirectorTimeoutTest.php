<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\AI\ProviderTransport;
use App\Services\AI\ProviderFailure;
use Illuminate\Support\Facades\Http;
class MarketingDirectorTimeoutTest extends TestCase
{
 public function test_transport_uses_bounded_timeout_and_identifies_curl_timeout():void{
  Http::fake(function($request,$options){$this->assertSame(90,$options['timeout']);throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');});
  try{app(ProviderTransport::class)->json('POST','https://api.openai.com/v1/responses',[],[],false,90);$this->fail('accepted');}catch(ProviderFailure $e){$this->assertSame('timeout',$e->reason);$this->assertStringNotContainsString('cURL',$e->getMessage());}
 }
}
