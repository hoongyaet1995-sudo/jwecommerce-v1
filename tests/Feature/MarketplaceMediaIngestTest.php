<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage};
use App\Services\Marketplace\MarketplaceMediaIngest;
class MarketplaceMediaIngestTest extends TestCase {
 use RefreshDatabase;
 public function test_verified_image_is_cached_and_records_its_source():void {
  Storage::fake('public');$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
  Http::fake(['*'=>Http::response($bytes)]);$service=app(MarketplaceMediaIngest::class);
  $url='https://cf.shopee.com.my/file/example';$managed=$service->ingest($url);
  $this->assertTrue(\App\Domain\Cms\MediaUrlPolicy::isManaged($managed));
  $this->assertSame($managed,$service->ingest($url));Http::assertSentCount(1);
  $this->assertDatabaseHas('marketplace_media_sources',['source_url'=>$url]);
 }
 public function test_private_and_nonimage_sources_cannot_be_ingested():void {
  Http::fake(['*'=>Http::response('<html>not an image</html>')]);
  $service=app(MarketplaceMediaIngest::class);
  foreach(['https://127.0.0.1/private','https://cf.shopee.com.my/file/error'] as $url){try{$service->ingest($url);$this->fail('Invalid image accepted');}catch(\DomainException){}}
  $this->assertDatabaseCount('cms_media_assets',0);
 }
}
