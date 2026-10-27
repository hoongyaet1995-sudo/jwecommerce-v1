<?php

namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingResearchBudget;
use App\Models\MarketingResearchRun;
use App\Services\AI\ProviderFailure;
use App\Services\Marketing\Research\ResearchProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingResearchProcessor
{
    public function __construct(private MarketingResearchWorkflow $flow, private ResearchProvider $provider, private MarketingResearchAnalyst $analyst, private MarketingResearchSources $sources) {}

    public function process(string $runId): void
    {
        $initial = MarketingResearchRun::findOrFail($runId);
        $claimed = DB::transaction(function () use ($initial) {
            $p = MarketingProject::lockForUpdate()->findOrFail($initial->project_id);
            $r = MarketingResearchRun::lockForUpdate()->findOrFail($initial->id);
            if ($r->status !== 'pending' || $p->active_research_run_id !== $r->id) { return false; }
            $r->update(['status' => 'running', 'started_at' => now()]);
            return true;
        });
        if (! $claimed) { return; }
        try {
            $run = $initial->fresh();
            $p = $this->flow->owned($run->project_id, $run->actor_id);
            if ($run->input_hash !== $this->flow->hash($this->flow->input($p))) { $this->finish($runId, 'failed', 'stale_input'); return; }
            $free = $run->pricing_snapshot['execution_mode'] === 'live_free_retrieval' && $this->flow->freeRetrieval($run->project_id);
            if (! config('marketing.research.enabled') || (! $free && ($run->pricing_snapshot['execution_mode'] !== 'simulated' || ! $this->flow->simulated()))) { $this->finish($runId, 'failed', 'execution_not_enabled'); return; }
            if ($free) { config(['marketing.research.free_probe_run' => $run->id]); }
            $input = $run->input_snapshot;
            $plan = $free ? $run->query_plan : $this->step($runId, 'plan', 'text', fn () => $this->analyst->plan($input, $runId));
            MarketingResearchRun::whereKey($runId)->update(['query_plan' => $plan]);
            $groups = [];
            foreach ($plan['queries'] as $index => $query) {
                $response = $this->step($runId, 'search'.$index, 'retrieval', fn () => $this->provider->search($query['query']));
                $groups[] = array_map(fn ($result) => array_replace($result, ['_role' => $query['role'], '_request_id' => $response['request_id']]), array_slice($response['results'], 0, 6));
            }
            // Round-robin preserves cross-industry sources inside the extraction budget.
            $pool = [];
            for ($i = 0; $i < 6; $i++) {
                foreach ($groups as $group) {
                    $item = $group[$i] ?? null;
                    if (is_array($item) && ($url = $this->sources->publicUrl((string) ($item['url'] ?? ''))) && ! isset($pool[$url])) { $pool[$url] = $item; }
                }
            }
            $this->saveSources($runId, $pool);
            foreach (array_chunk(array_slice(array_keys($pool), 0, 10), 5) as $index => $batch) {
                foreach ($batch as $url) { $pool[$url]['_extraction_status'] = 'pending'; }
                $this->saveSources($runId, $pool);
                $response = $this->step($runId, 'extract'.$index, 'retrieval', fn () => $this->provider->extract($batch));
                foreach ($batch as $url) { $pool[$url]['_extraction_status'] = 'failed'; $pool[$url]['_extraction_error'] = 'not_returned'; }
                foreach ($response['failed_results'] as $failure) {
                    if (in_array($failure['url'], $batch, true)) { $pool[$failure['url']]['_extraction_error'] = 'content_unavailable'; }
                }
                foreach ($response['results'] as $item) {
                    $url = $this->sources->publicUrl((string) ($item['url'] ?? ''));
                    if ($url && in_array($url, $batch, true) && is_string($item['raw_content'] ?? null)) {
                        $pool[$url]['raw_content'] = $item['raw_content'];
                        $pool[$url]['_request_id'] = $response['request_id'];
                        $pool[$url]['_extraction_status'] = trim($item['raw_content']) !== '' ? 'content_available' : 'empty';
                        $pool[$url]['_extraction_error'] = trim($item['raw_content']) !== '' ? null : 'empty_content';
                    }
                }
                $this->saveSources($runId, $pool);
            }
            $sources = MarketingResearchRun::findOrFail($runId)->sources;
            $analysis = $free ? ['business_facts' => $input, 'coverage' => array_replace($this->sources->coverage($sources), ['status' => count($this->sources->analysisSources($sources)) ? 'partial' : 'insufficient']), 'text_ai_analyzed' => false, 'audience_hypotheses' => [], 'observations' => [], 'adaptation_methods' => [], 'limitations' => ['Live free-credit retrieval only. Query plan was supplied for acceptance; no text AI analysis or video playback was performed.']] : ($this->sources->coverage($sources)['status'] === 'insufficient'
                ? $this->analyst->analyze($input, $sources, $runId)
                : $this->step($runId, 'analysis', 'text', fn () => $this->analyst->analyze($input, $sources, $runId)));
            $this->finish($runId, $analysis['coverage']['status'], null, $analysis);
        } catch (ProviderFailure $error) {
            $uncertain = in_array($error->reason, ['unavailable', 'response'], true);
            $this->finish($runId, $uncertain ? 'unknown' : 'failed', $uncertain ? 'provider_outcome_unknown' : 'provider_'.$error->reason);
        } catch (ValidationException $error) {
            $this->finish($runId, 'failed', array_key_exists('stale_input', $error->errors()) ? 'stale_input' : 'invalid_research_output');
        } catch (\Throwable) {
            // Never retain raw HTTP exceptions, headers or provider response bodies.
            $this->unknown($runId);
        }
    }

    private function saveSources(string $run, array $pool): void
    {
        MarketingResearchRun::whereKey($run)->where('status', 'running')->update(['sources' => $this->sources->normalize(array_values($pool))]);
    }

    private function step(string $runId, string $name, string $kind, callable $call): array
    {
        $run = MarketingResearchRun::findOrFail($runId);
        DB::transaction(function () use ($run, $name, $kind) {
            $p = MarketingProject::lockForUpdate()->findOrFail($run->project_id);
            $r = MarketingResearchRun::lockForUpdate()->findOrFail($run->id);
            if ($r->input_hash !== $this->flow->hash($this->flow->input($p))) { throw ValidationException::withMessages(['stale_input' => 'The confirmed inputs changed.']); }
            if ($r->status !== 'running' || $p->active_research_run_id !== $r->id || isset($r->calls[$name])) { throw ValidationException::withMessages(['research' => 'This request step cannot be repeated.']); }
            $cost = $r->pricing_snapshot[$kind === 'text' ? 'text_call_cap_microusd' : 'retrieval_call_cap_microusd'];
            $calls = $r->calls;
            if (count($calls) >= 7 || array_sum(array_column($calls, 'cost_upper_microusd')) + $cost > $r->reserved_microusd) { throw ValidationException::withMessages(['research' => 'The bounded request budget is exhausted.']); }
            $calls[$name] = ['status' => 'pending', 'kind' => $kind, 'cost_upper_microusd' => $cost, 'started_at' => now()->toIso8601String()];
            $r->update(['calls' => $calls]);
        });
        request()->attributes->remove('ai_provider_usage');
        $result = $call();
        DB::transaction(function () use ($runId, $name, $result) {
            $r = MarketingResearchRun::lockForUpdate()->findOrFail($runId);
            if ($r->status !== 'running') { throw ValidationException::withMessages(['research' => 'The request outcome was already recorded.']); }
            $calls = $r->calls;
            $calls[$name] = array_replace($calls[$name], ['status' => 'completed', 'finished_at' => now()->toIso8601String(), 'request_id' => $result['request_id'] ?? null, 'usage_credits' => $result['usage_credits'] ?? null, 'token_usage' => request()->attributes->get('ai_provider_usage', [])]);
            $r->update(['calls' => $calls, 'reported_usage' => array_map(fn ($c) => array_intersect_key($c, array_flip(['usage_credits', 'token_usage'])), $calls)]);
        });
        return $result;
    }

    private function finish(string $runId, string $status, ?string $error = null, ?array $analysis = null): void
    {
        $initial = MarketingResearchRun::findOrFail($runId);
        DB::transaction(function () use ($initial, $status, $error, $analysis) {
            $p = MarketingProject::lockForUpdate()->findOrFail($initial->project_id);
            $r = MarketingResearchRun::lockForUpdate()->findOrFail($initial->id);
            if (! in_array($r->status, ['pending', 'running'], true)) { return; }
            if (in_array($status, ['available', 'partial'], true) && ($p->active_research_run_id !== $r->id || $r->input_hash !== $this->flow->hash($this->flow->input($p)))) { $status = 'failed'; $error = 'stale_input'; }
            if ($status !== 'unknown') {
                $budget = MarketingResearchBudget::lockForUpdate()->findOrFail($r->budget_id);
                // Conservative accounted upper bound; this is NOT an actual invoice.
                $accounted = array_sum(array_column($r->calls ?? [], 'cost_upper_microusd'));
                $budget->update(['held_microusd' => $budget->held_microusd - $r->reserved_microusd, 'settled_microusd' => $budget->settled_microusd + $accounted]);
            }
            $r->update(['status' => $status, 'error_code' => $error, 'analysis' => $analysis, 'finished_at' => now()]);
            if ($p->active_research_run_id === $r->id) {
                $updates = ['active_research_run_id' => null, 'version' => $p->version + 1];
                if (in_array($status, ['available', 'partial'], true)) { $updates['current_research_run_id'] = $r->id; }
                $p->update($updates);
            }
        });
    }

    public function unknown(string $runId): void { $this->finish($runId, 'unknown', 'interrupted_outcome_unknown'); }
}
