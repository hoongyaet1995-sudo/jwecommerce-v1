<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingResearchSources;
use App\Services\Marketing\Research\TavilyResearchProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingResearchProviderTest extends TestCase
{
    use RefreshDatabase, MarketingResearchFixtures;

    public function test_extract_200_partial_and_all_failed_are_not_content_success(): void
    {
        $this->researchProject();
        Http::fake(['api.tavily.com/extract' => Http::sequence()->push(['results' => [['url' => 'https://example.com/film', 'raw_content' => 'Public supplier description']], 'failed_results' => [['url' => 'https://example.org/unavailable', 'error' => 'raw sensitive response']], 'request_id' => 'public-receipt'])->push(['results' => [], 'failed_results' => [['url' => 'https://example.com/film']]])]);
        $client = app(TavilyResearchProvider::class);
        $a = $client->extract(['https://example.com/film', 'https://example.org/unavailable']);
        $this->assertCount(1, $a['results']);
        $this->assertSame('content_unavailable', $a['failed_results'][0]['error']);
        $this->assertNull($a['usage_credits']);
        $this->assertSame([], $client->extract(['https://example.com/film'])['results']);
        Http::assertSent(fn ($r) => $r['extract_depth'] === 'basic' && $r['include_usage'] === true && count($r['urls']) <= 5);
    }

    public function test_snippet_and_description_never_become_video_observation(): void
    {
        $normalizer = app(MarketingResearchSources::class);
        $sources = $normalizer->normalize([
            ['url' => 'https://www.youtube.com/watch?v=abc123&feature=share', 'content' => 'a viral claim', 'observation_level' => 'video_observed', 'views' => 999999],
            ['url' => 'https://example.com/film', 'raw_content' => 'A product description.'],
            ['url' => 'https://example.org/watch', 'raw_content' => 'Video Transcript: The product is shown. https://www.youtube.com/watch?v=abc123'],
        ]);
        $this->assertSame(['search_snippet', 'page_description', 'publisher_transcript_unverified_sync'], array_column($sources, 'observation_level'));
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $sources[0]['canonical_url']);
        $this->assertNull($sources[0]['metrics']['views']['value']);
        $this->assertSame('research_only', $sources[0]['asset_rights']);
        foreach (['http://example.com', 'https://127.0.0.1/admin', 'https://[::1]/', 'https://user:pass@example.com', 'https://localhost/', 'https://service.local/', 'https://example.com/?api_key=secret', 'https://example.com:8443/'] as $url) {
            $this->assertNull($normalizer->publicUrl($url));
        }
    }

    public function test_search_uses_fixed_basic_parameters_and_rejects_unsafe_extract_urls(): void
    {
        $this->researchProject();
        Http::fake(['api.tavily.com/search' => Http::response(['results' => [], 'usage' => ['credits' => 1], 'request_id' => 'receipt'])]);
        $client = app(TavilyResearchProvider::class);
        $this->assertSame(1, $client->search('Furniture demonstration in Malaysia')['usage_credits']);
        Http::assertSent(fn ($r) => $r['search_depth'] === 'basic' && $r['max_results'] === 6 && $r['auto_parameters'] === false && $r['include_answer'] === false);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $client->extract(['https://127.0.0.1/secret']);
    }

    public function test_multiple_pages_for_one_video_do_not_inflate_coverage(): void
    {
        $normalizer = app(MarketingResearchSources::class);
        $sources = $normalizer->normalize(array_map(fn ($i) => ['url' => 'https://example.com/ref'.$i, 'raw_content' => "Video Transcript:\nA demonstration. https://www.youtube.com/watch?v=sharedvideo", '_role' => $i === 4 ? 'cross_industry' : 'same_industry'], [1, 2, 3, 4]));
        $this->assertSame('partial', $normalizer->coverage($sources)['status']);
        $this->assertSame(1, $normalizer->coverage($sources)['publisher_transcripts']);
    }

    public function test_search_cannot_claim_an_extracted_body_or_spoof_youtube_platform(): void
    {
        $this->researchProject();
        Http::fake(['api.tavily.com/search' => Http::response(['results' => [['url' => 'https://notyoutube.com/watch', 'raw_content' => 'Video Transcript: https://youtube.com/watch?v=sharedvideo', 'title' => 'Description']]])]);
        $result = app(TavilyResearchProvider::class)->search('Public product videos');
        $source = app(MarketingResearchSources::class)->normalize($result['results'])[0];
        $this->assertSame('search_snippet', $source['observation_level']);
        $this->assertSame('publisher_web', $source['platform']);
    }

    public function test_sanitized_extraction_failure_survives_source_normalization(): void
    {
        $source = app(MarketingResearchSources::class)->normalize([['url' => 'https://example.com/film', 'content' => 'Description', '_extraction_status' => 'failed', '_extraction_error' => 'content_unavailable']])[0];
        $this->assertSame('failed', $source['extraction_status']);
        $this->assertSame('content_unavailable', $source['extraction_error_code']);
    }
}
