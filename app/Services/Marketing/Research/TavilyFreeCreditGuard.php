<?php

namespace App\Services\Marketing\Research;

use App\Models\MarketingResearchRun;
use App\Services\AI\ProviderTransport;
use App\Services\Marketing\MarketingResearchWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TavilyFreeCreditGuard
{
    public function __construct(private ProviderTransport $http) {}

    public function validateUsage(array $data): array
    {
        $a = $data['account'] ?? [];
        $k = $data['key'] ?? [];
        $valid = in_array(strtolower($a['current_plan'] ?? ''), ['researcher', 'free'], true)
            && is_numeric($a['plan_usage'] ?? null) && is_numeric($a['plan_limit'] ?? null)
            && $a['plan_usage'] >= 0 && $a['plan_limit'] - $a['plan_usage'] >= 10
            && is_numeric($a['paygo_usage'] ?? null) && (float) $a['paygo_usage'] === 0.0;
        if (isset($k['limit'])) { $valid = $valid && is_numeric($k['limit']) && is_numeric($k['usage'] ?? null) && $k['limit'] - $k['usage'] >= 5; }
        if (! $valid) { throw ValidationException::withMessages(['research' => 'A verified free account with sufficient remaining credits is required. No upgrade or paid fallback is allowed.']); }
        return ['current_plan' => $a['current_plan'], 'plan_usage' => 0 + $a['plan_usage'], 'plan_limit' => 0 + $a['plan_limit'], 'paygo_usage' => 0, 'key_usage' => $k['usage'] ?? null, 'key_limit' => $k['limit'] ?? null];
    }

    public function usage(): array
    {
        return $this->validateUsage($this->http->json('GET', 'https://api.tavily.com/usage', ['Authorization' => 'Bearer '.config('marketing.research.api_key')]));
    }

    public function authorizeStep(): void
    {
        $run = MarketingResearchRun::find(config('marketing.research.free_probe_run'));
        if (! $run || ! app(MarketingResearchWorkflow::class)->freeRetrieval($run->project_id) || ($run->pricing_snapshot['execution_mode'] ?? '') !== 'live_free_retrieval') {
            throw ValidationException::withMessages(['research' => 'Free retrieval is limited to one explicitly scoped local project.']);
        }
        $usage = $this->usage();
        $initial = $run->pricing_snapshot['free_usage_before']['plan_usage'];
        if ($usage['plan_usage'] - $initial + 1 > 5) { throw ValidationException::withMessages(['research' => 'The five-credit acceptance allowance is exhausted.']); }
        DB::transaction(function () use ($run, $usage) {
            $r = MarketingResearchRun::lockForUpdate()->findOrFail($run->id);
            $calls = $r->calls;
            $pending = array_filter($calls, fn ($c) => $c['status'] === 'pending' && $c['kind'] === 'retrieval');
            if ($r->status !== 'running' || count($calls) > 5 || count($pending) !== 1) { throw ValidationException::withMessages(['research' => 'This free retrieval request cannot be sent.']); }
            $name = array_key_first($pending);
            if ($calls[$name]['transport_started'] ?? false) { throw ValidationException::withMessages(['research' => 'This request was already sent; its outcome must be checked.']); }
            $calls[$name]['transport_started'] = true;
            $calls[$name]['free_usage_before'] = $usage;
            $r->update(['calls' => $calls]);
        });
    }
}
