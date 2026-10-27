<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingResearchSources;
use Tests\TestCase;

class MarketingResearchEvidenceTest extends TestCase
{
    public function test_direct_video_transcript_heading_and_timestamps_are_recognized(): void
    {
        $service = app(MarketingResearchSources::class);
        $sources = $service->normalize([['url' => 'https://www.youtube.com/watch?v=J7OAsMsThSM', 'raw_content' => "# Product tour\n### Description\nA tour.\n### Transcript\n[0:02] First explain the product.\n[0:18] Then explain a real use.", '_role' => 'same_industry']]);
        $this->assertSame('publisher_transcript_unverified_sync', $sources[0]['observation_level']);
        $this->assertSame('youtube:J7OAsMsThSM', $sources[0]['video_identity']);
        $this->assertSame('partial', $service->coverage($sources)['status']);
        $this->assertSame(0, $service->coverage($sources)['verified_current_viral']);
    }

    public function test_heading_alone_chapters_and_embedded_unrelated_links_are_not_transcripts(): void
    {
        $service = app(MarketingResearchSources::class);
        foreach (["### Transcript\nLoading...", "### Chapters\n[0:00] Intro\n[0:20] Features", "Mention transcript in prose. [0:00] A chapter", "### Transcript\n[0:00] One caption"] as $body) {
            $this->assertSame('page_description', $service->normalize([['url' => 'https://youtube.com/watch?v=J7OAsMsThSM', 'raw_content' => $body]])[0]['observation_level']);
        }
        $source = $service->normalize([['url' => 'https://example.com/article', 'raw_content' => "### Transcript\n[0:00] First phrase\n[0:10] Second phrase\nhttps://youtube.com/watch?v=J7OAsMsThSM"]])[0];
        $this->assertSame('page_description', $source['observation_level']);
    }

    public function test_functional_playlist_parameter_survives_without_tracking_or_credentials(): void
    {
        $service = app(MarketingResearchSources::class);
        $this->assertSame('https://www.youtube.com/playlist?list=PLexample', $service->publicUrl('https://www.youtube.com/playlist?list=PLexample&utm_source=tracking'));
        $this->assertNull($service->publicUrl('https://www.youtube.com/playlist?list=PLexample&access_token=private'));
    }

    public function test_failed_extraction_cannot_supply_evidence_even_with_transcript_body(): void
    {
        $service = app(MarketingResearchSources::class);
        $source = $service->normalize([['url' => 'https://youtube.com/watch?v=J7OAsMsThSM', 'raw_content' => "### Transcript\n[0:00] A product\n[0:10] A real use", '_extraction_status' => 'failed', '_extraction_error' => 'content_unavailable']])[0];
        $this->assertSame('search_snippet', $source['observation_level']);
        $this->assertCount(0, $service->analysisSources([$source]));
    }

    public function test_quality_distinguishes_transcript_from_performance_and_playback(): void
    {
        $service = app(MarketingResearchSources::class);
        $source = $service->normalize([['url' => 'https://youtube.com/watch?v=J7OAsMsThSM', 'raw_content' => "### Transcript\n[0:00] A product\n[0:10] A real use", '_role' => 'same_industry']])[0];
        $this->assertSame('transcript_text_only', $source['quality']['basis']);
        $this->assertFalse($source['quality']['performance_verified']);
        $this->assertFalse($source['quality']['playback_observed']);
        $this->assertNull($source['metrics']['views']['value']);
    }

    public function test_explicit_transcript_heading_requires_content_not_placeholder_or_link_only(): void
    {
        $service = app(MarketingResearchSources::class);
        foreach (["Video Transcript:\nLoading...", "Video Transcript:\nTranscript unavailable.", "Video Transcript:\nhttps://youtube.com/watch?v=J7OAsMsThSM"] as $body) {
            $source = $service->normalize([['url' => 'https://youtube.com/watch?v=J7OAsMsThSM', 'raw_content' => $body]])[0];
            $this->assertSame('page_description', $source['observation_level']);
            $this->assertCount(0, $service->analysisSources([$source]));
        }
    }
}
