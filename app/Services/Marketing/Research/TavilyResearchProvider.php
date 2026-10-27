<?php

namespace App\Services\Marketing\Research;

use App\Services\AI\ProviderFailure;
use App\Services\AI\ProviderTransport;
use App\Services\Marketing\MarketingResearchSources;
use App\Services\Marketing\MarketingResearchWorkflow;
use Illuminate\Validation\ValidationException;

class TavilyResearchProvider implements ResearchProvider
{
    public function __construct(private ProviderTransport $http, private MarketingResearchSources $sources) {}

    private function call(string $endpoint, array $payload): array
    {
        $flow = app(MarketingResearchWorkflow::class);
        if ($flow->simulated()) { $flow->simulationTransport(); }
        else { app(TavilyFreeCreditGuard::class)->authorizeStep(); }
        $data = $this->http->json('POST', 'https://api.tavily.com/'.$endpoint, ['Authorization' => 'Bearer '.config('marketing.research.api_key')], $payload);
        if (! isset($data['results']) || ! is_array($data['results'])) { throw new ProviderFailure('response'); }
        $credits = $data['usage']['credits'] ?? null;
        return ['request_id' => is_string($data['request_id'] ?? null) ? mb_substr($data['request_id'], 0, 150) : null,
            'results' => array_values(array_filter($data['results'], 'is_array')),
            'failed_results' => array_map(fn ($item) => ['url' => $this->sources->publicUrl((string) ($item['url'] ?? '')), 'error' => 'content_unavailable'], array_filter(is_array($data['failed_results'] ?? null) ? $data['failed_results'] : [], 'is_array')),
            'usage_credits' => is_numeric($credits) && $credits >= 0 ? 0 + $credits : null];
    }

    public function search(string $query): array
    {
        if (trim($query) === '' || mb_strlen($query) > 180) { throw ValidationException::withMessages(['research' => 'The public search query is invalid.']); }
        $data = $this->call('search', ['query' => $query, 'search_depth' => 'basic', 'max_results' => 6, 'auto_parameters' => false, 'include_answer' => false, 'include_raw_content' => false, 'include_images' => false, 'include_usage' => true]);
        $data['results'] = array_map(fn ($item) => array_intersect_key($item, array_flip(['url', 'title', 'content'])), array_slice($data['results'], 0, 6));
        return $data;
    }

    public function extract(array $urls): array
    {
        $safe = array_map(fn ($url) => is_string($url) ? $this->sources->publicUrl($url) : null, $urls);
        if (! count($urls) || count($urls) > 5 || in_array(null, $safe, true)) { throw ValidationException::withMessages(['research' => 'Only a small batch of public HTTPS sources can be extracted.']); }
        return $this->call('extract', ['urls' => array_values($safe), 'extract_depth' => 'basic', 'include_images' => false, 'include_usage' => true]);
    }
}
