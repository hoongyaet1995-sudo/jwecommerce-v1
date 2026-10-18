<?php

namespace Tests\Feature;

use App\Models\SearchExposureObservation;
use App\Services\Search\SearchExposureBaseline;
use App\Services\Search\SearchPilotAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SearchExposureBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_unmeasured_platforms_are_null_and_real_observations_persist_separately_from_refresh(): void
    {
        $service = app(SearchExposureBaseline::class);
        $saved = $service->record(['platform' => 'ChatGPT', 'product_surface' => 'consumer_web', 'search_mode' => 'web_confirmed', 'language' => 'zh', 'market' => 'Malaysia', 'query' => 'Container suppliers?', 'observed_at' => now()->toIso8601String(), 'status' => 'observed', 'answer' => 'Supplier Example', 'citation_urls' => ['https://supplier.example/about'], 'evidence_reference' => 'local-evidence.json']);
        $this->assertNotEmpty($saved['id']);
        $report = $service->report();
        $this->assertSame(1, $report['platforms']['ChatGPT']['observed']);
        $this->assertNull($report['platforms']['Claude']['mention_rate']);
        $this->assertSame(0, $report['platforms']['Claude']['observed']);
        app(SearchPilotAudit::class)->capture();
        $this->assertSame(1, SearchExposureObservation::count());
        $same = $service->record(array_intersect_key($saved, array_flip(['platform', 'product_surface', 'search_mode', 'language', 'market', 'query', 'observed_at', 'status', 'answer', 'citation_urls', 'evidence_reference'])));
        $this->assertSame($saved['id'], $same['id']);
        $this->assertSame(1, SearchExposureObservation::count());
    }

    public function test_a_claimed_observation_requires_answer_and_evidence(): void
    {
        $this->expectException(ValidationException::class);
        app(SearchExposureBaseline::class)->record(['platform' => 'Claude', 'status' => 'observed']);
    }

    public function test_import_identity_is_stable_across_key_order_and_explicit_defaults(): void
    {
        $service = app(SearchExposureBaseline::class);
        $input = ['platform' => 'ChatGPT', 'product_surface' => 'consumer_web', 'search_mode' => 'web_confirmed', 'language' => 'zh', 'market' => 'Malaysia', 'query' => 'Container suppliers?', 'observed_at' => now()->toIso8601String(), 'status' => 'observed', 'answer' => 'Supplier Example', 'citation_urls' => [], 'evidence_reference' => 'same-evidence.json', 'target_origin' => 'https://demo.example'];
        $first = $service->record($input);
        $input['scope'] = 'demo';
        $second = $service->record(array_reverse($input, true));
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, SearchExposureObservation::count());
    }

    public function test_audit_does_not_hydrate_historical_answer_payloads(): void
    {
        DB::enableQueryLog();
        app(SearchPilotAudit::class)->report();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'search_exposure_observations'));
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('payload', $sql);
            $this->assertStringNotContainsString('select *', $sql);
        }
        DB::disableQueryLog();
    }
}
