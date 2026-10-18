<?php

namespace App\Services\Search;

use App\Models\SearchExposureObservation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Evidence ledger only. This service never simulates another AI product's answer. */
class SearchExposureBaseline
{
    public const PLATFORMS = ['ChatGPT', 'Gemini', 'Claude', 'DeepSeek'];

    public function record(array $input): array
    {
        $data = Validator::make($input, [
            'platform' => ['required', Rule::in(self::PLATFORMS)],
            'product_surface' => ['required', Rule::in(['consumer_web', 'api'])],
            'search_mode' => ['required', Rule::in(['web_confirmed', 'requested_unconfirmed', 'unavailable'])],
            'language' => 'required|string|max:30', 'market' => 'required|string|max:120',
            'query' => 'required|string|max:2000', 'observed_at' => 'required|date|before_or_equal:now',
            'status' => ['required', Rule::in(['observed', 'blocked'])],
            'answer' => 'required_if:status,observed|nullable|string|max:60000',
            'reason' => 'required_if:status,blocked|nullable|string|max:1000',
            'citation_urls' => 'present|array|max:100', 'citation_urls.*' => 'required|url:http,https|max:2000',
            'evidence_reference' => 'required|string|max:1000',
            'model_if_visible' => 'nullable|string|max:120',
            'scope' => ['sometimes', Rule::in(['demo', 'customer'])],
            'query_type' => ['sometimes', Rule::in(['discovery', 'brand_check', 'answer_research'])],
            'target_origin' => 'nullable|url:http,https|max:500',
        ])->validate();
        $data['id'] = (string) Str::uuid();
        $data['recorded_at'] = now()->toIso8601String();
        $data['brand'] = app(BusinessIdentity::class)->read()['name'];
        $data['brand_accuracy'] = 'not_reviewed';
        $data['brand_mentioned'] = $data['status'] === 'observed' ? Str::contains(Str::lower($data['answer']), Str::lower($data['brand'])) : null;
        $data['scope'] = $data['scope'] ?? 'demo';
        $data['target_origin'] = $data['target_origin'] ?? app(SearchSettings::class)->origin();
        $targetHost = strtolower((string) parse_url($data['target_origin'], PHP_URL_HOST));
        $data['target_cited'] = $data['status'] === 'observed'
            ? collect($data['citation_urls'])->contains(fn ($url) => strtolower((string) parse_url($url, PHP_URL_HOST)) === $targetHost)
            : null;
        $fingerprintData = [];
        foreach (['platform', 'product_surface', 'query', 'observed_at', 'status', 'answer', 'evidence_reference', 'scope', 'target_origin'] as $field) {
            $fingerprintData[$field] = $data[$field] ?? null;
        }
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $row = SearchExposureObservation::firstOrCreate(['fingerprint' => $fingerprint], ['id' => $data['id'], 'platform' => $data['platform'], 'status' => $data['status'], 'observed_at' => $data['observed_at'], 'payload' => $data]);

        return $row->payload;
    }

    public function report(): array
    {
        $records = SearchExposureObservation::orderByDesc('observed_at')->orderBy('id')->limit(1000)->pluck('payload')->all();

        return ['schema_version' => 1, 'platforms' => $this->counts(), 'observations' => $records, 'total_observations' => SearchExposureObservation::count(), 'returned_observations' => count($records),
            'note' => 'Counts describe recorded samples only. Missing data is not zero visibility. Compare matching query/language/market/surface/search mode and domain cohorts. Prompted brand checks are separate from non-brand discovery.'];
    }

    public function counts(): array
    {
        $totals = SearchExposureObservation::selectRaw('platform, status, count(*) as aggregate')->groupBy('platform', 'status')->get();
        $platforms = [];
        foreach (self::PLATFORMS as $platform) {
            $rows = $totals->where('platform', $platform)->keyBy('status');
            $platforms[$platform] = ['observed' => (int) ($rows->get('observed')?->aggregate ?? 0), 'blocked' => (int) ($rows->get('blocked')?->aggregate ?? 0),
                'mention_rate' => null]; // No misleading score across different prompts, domains, scopes or surfaces.
        }

        return $platforms;
    }
}
