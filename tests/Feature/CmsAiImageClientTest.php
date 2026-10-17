<?php
namespace Tests\Feature;
use App\Services\AI\CmsAiImageClient;
use App\Services\AI\ProviderFailure;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsAiImageClientTest extends TestCase
{
    public function test_image_generation_uses_one_image_and_returns_validated_bytes(): void
    {
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6XbUAAAAASUVORK5CYII=');
        Http::fake(['api.openai.com/v1/images/generations'=>Http::response(['data'=>[['b64_json'=>base64_encode($png)]],'usage'=>['total_tokens'=>10]])]);
        $result=(new CmsAiImageClient)->generate('fixture-key','gpt-image-2','Illustrative scene','medium','16:9');
        $this->assertSame($png,$result['bytes']);
        Http::assertSent(fn($r)=>$r['n']===1&&$r['size']==='1536x864'&&!isset($r['response_format']));
    }
    public function test_provider_failure_does_not_expose_credentials_or_raw_body(): void
    {
        Http::fake(['*'=>Http::response(['error'=>'fixture-secret'],401)]);
        $this->expectException(ProviderFailure::class);
        (new CmsAiImageClient)->generate('fixture-key','gpt-image-2','Scene','medium','4:3');
    }
    public function test_a_remote_url_is_not_treated_as_generated_image_bytes(): void
    {
        Http::fake(['*'=>Http::response(['data'=>[['url'=>'https://private.test/image']]])]);
        $this->expectException(ProviderFailure::class);
        (new CmsAiImageClient)->generate('fixture-key','gpt-image-2','Scene','medium','4:3');
    }
}
