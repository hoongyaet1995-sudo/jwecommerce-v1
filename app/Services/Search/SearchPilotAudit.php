<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use App\Models\StoreSetting;

/** Internal operational report, not a claim about external indexing or AI recommendations. */
class SearchPilotAudit
{
    public function report(): array
    {
        $settings = app(SearchSettings::class);
        $metadata = app(SearchMetadata::class);
        $identity = app(BusinessIdentity::class)->read();
        $preview = $settings->read()['preview'];
        $records = [];
        foreach (['product' => Product::class, 'category' => Category::class, 'collection' => Collection::class, 'page' => Page::class] as $kind => $class) {
            $class::query()->chunkById(100, function ($rows) use ($kind, $metadata, $settings, $preview, &$records) {
                foreach ($rows as $row) {
                    if (! $metadata->public($kind, $row->id)) {
                        continue;
                    }
                    $data = $metadata->for($kind, $row->id, $kind === 'page' ? 'published' : 'live');
                    $records[] = ['kind' => $kind, 'id' => $row->id, 'url' => $settings->origin().$metadata->path($kind, $row->id),
                        'eligible' => ! $data['excluded'] && ! $preview,
                        'has_title' => $data['rawTitle'] !== '', 'has_description' => $data['description'] !== '',
                        'source' => $data['source']];
                }
            });
        }
        $host = parse_url($settings->origin(), PHP_URL_HOST);
        $exposure = app(SearchExposureBaseline::class)->counts();
        $observed = array_sum(array_column($exposure, 'observed'));
        $local = ! $host || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.local')
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));

        return ['schema_version' => 1, 'checked_at' => now()->toIso8601String(), 'origin' => $settings->origin(), 'identity' => $identity,
            'environment' => $local ? 'local_not_publicly_indexable' : 'public_origin_not_yet_verified',
            'indexing' => ['status' => 'not_verified', 'evidence_required' => 'Verified customer-domain Google Search Console / Bing URL inspection. HTTP success and sitemap presence alone are not index evidence.'],
            'records' => $records,
            'ai_baseline' => ['status' => $observed ? 'partial_observations' : 'not_measured', 'platforms' => ['ChatGPT', 'Gemini', 'Claude', 'DeepSeek'],
                'measurement_contract' => ['platform', 'product_surface', 'model_if_visible', 'search_mode', 'language', 'market', 'query', 'observed_at', 'answer', 'citation_urls', 'brand_mentioned', 'brand_accuracy', 'evidence_reference'],
                'note' => 'Missing observations are not zero visibility. API and consumer UI results must remain separate.'],
            'exposure_evidence' => $exposure,
        ];
    }

    public function capture(): array
    {
        $report = $this->report();
        StoreSetting::updateOrCreate(['key' => 'search_pilot_audit'], ['value' => $report]);

        return $report;
    }
}
