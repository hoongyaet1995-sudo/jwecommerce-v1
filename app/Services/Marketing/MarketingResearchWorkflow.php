<?php

namespace App\Services\Marketing;

use App\Jobs\ProcessMarketingResearch;
use App\Models\MarketingProject;
use App\Models\MarketingResearchBudget;
use App\Models\MarketingResearchRun;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingResearchWorkflow
{
    public function owned(string $project, int $actor): MarketingProject
    {
        abort_unless(User::whereKey($actor)->where('is_super_admin', true)->exists(), 403);
        return MarketingProject::where('owner_id', $actor)->findOrFail($project);
    }

    public function input(MarketingProject $p): array
    {
        return ['snapshot' => Arr::only($p->snapshot, ['title', 'description', 'subtitle', 'purchase_mode']),
            'brief' => Arr::only($p->brief, ['source_confirmed', 'facts_confirmed', 'goal_confirmed', 'audience_confirmed', 'facts', 'source_summary', 'goal', 'goal_reason', 'cta', 'audience', 'market', 'language', 'platforms'])];
    }

    public function hash(array $input): string
    {
        $sort = function (array $data) use (&$sort): array {
            if (! array_is_list($data)) { ksort($data); }
            foreach ($data as &$value) { if (is_array($value)) { $value = $sort($value); } }
            return $data;
        };
        return hash('sha256', json_encode($sort($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function simulated(): bool
    {
        return (bool) config('marketing.research.simulated') && (app()->runningUnitTests() || (app()->runningInConsole() && app()->environment('local')));
    }

    public function simulationTransport(): void
    {
        if (! $this->simulated()) {
            throw ValidationException::withMessages(['research' => 'Live research is not accepted yet.']);
        }
        // Even an accidental missing fixture cannot spend money during simulation.
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }

    public function freeRetrieval(string $project): bool
    {
        return ! $this->simulated() && (bool) config('marketing.research.free_probe_enabled')
            && config('marketing.research.free_probe_project') === $project
            && (app()->runningUnitTests() || (app()->runningInConsole() && app()->environment('local')));
    }

    public function start(string $project, int $actor, int $version, string $requestId): MarketingResearchRun
    {
        $this->owned($project, $actor);
        if (! config('marketing.research.enabled') || (! $this->simulated() && ! $this->freeRetrieval($project) && ! config('marketing.research.live_enabled'))) {
            throw ValidationException::withMessages(['research' => 'Research is not enabled for this environment. Saved work remains unchanged.']);
        }
        // Read-only quota check happens outside project/budget locks.
        $freeUsage = $this->freeRetrieval($project) ? app(\App\Services\Marketing\Research\TavilyFreeCreditGuard::class)->usage() : null;
        $queries = $freeUsage !== null ? app(MarketingResearchAnalyst::class)->validatePlan(config('marketing.research.approved_queries') ?? []) : null;
        $run = DB::transaction(function () use ($project, $actor, $version, $requestId, $freeUsage, $queries) {
            $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($project);
            $input = $this->input($p);
            $hash = $this->hash($input);
            $old = MarketingResearchRun::where('project_id', $project)->where('request_id', $requestId)->first();
            if ($old) {
                abort_unless($old->input_hash === $hash, 409, 'This request belongs to different project information.');
                return $old;
            }
            abort_if($freeUsage !== null && MarketingResearchRun::where('project_id', $project)->where('pricing_snapshot->execution_mode', 'live_free_retrieval')->exists(), 409, 'This project already used its single free-credit acceptance request.');
            abort_if($p->version !== $version || $p->busy_run_id || $p->active_research_run_id, 409, 'The project changed or has an active request.');
            $b = $p->brief;
            abort_unless(($b['source_confirmed'] ?? false) && ($b['facts_confirmed'] ?? false) && ($b['goal_confirmed'] ?? false) && ($b['audience_confirmed'] ?? false) && trim($b['market'] ?? '') && trim($b['language'] ?? '') && count($b['platforms'] ?? []), 422, 'Confirm the project information first.');
            $textCap = (int) config('marketing.research.text_call_cap_microusd');
            $pricing = ['reference' => config('marketing.research.pricing_reference'), 'verified_at' => config('marketing.research.pricing_verified_at'), 'text_model' => config('marketing.research.text_model'), 'text_call_cap_microusd' => $textCap, 'retrieval_call_cap_microusd' => 8000, 'execution_mode' => $this->simulated() ? 'simulated' : 'live'];
            if ($freeUsage !== null) { $pricing = ['reference' => 'User-authorized Free plan retrieval test', 'verified_at' => now()->toIso8601String(), 'text_model' => null, 'text_call_cap_microusd' => 0, 'retrieval_call_cap_microusd' => 0, 'execution_mode' => 'live_free_retrieval', 'credit_cap' => 5, 'free_usage_before' => $freeUsage]; }
            if (! config('marketing.research.api_key') || ($freeUsage === null && ($textCap < 1 || ! $pricing['reference'] || ! $pricing['verified_at'] || ! $pricing['text_model']))) {
                throw ValidationException::withMessages(['research' => 'Research credentials and independently approved pricing are required.']);
            }
            // Live remains unavailable until a separately verified price contract is implemented.
            if (! $this->simulated() && $freeUsage === null) {
                throw ValidationException::withMessages(['research' => 'Live research pricing and account acceptance are still pending.']);
            }
            $budget = MarketingResearchBudget::lockForUpdate()->find(config('marketing.research.budget_id'));
            $reserve = $freeUsage !== null ? 0 : 40000 + 2 * $textCap;
            if (! $budget || ! $budget->enabled || $budget->currency !== 'USD' || trim($budget->authorization_reference) === '' || $budget->approved_microusd - $budget->held_microusd - $budget->settled_microusd < $reserve) {
                throw ValidationException::withMessages(['research' => 'The independent research budget cannot cover this request.']);
            }
            $budget->update(['held_microusd' => $budget->held_microusd + $reserve]);
            $run = MarketingResearchRun::create(['project_id' => $project, 'actor_id' => $actor, 'request_id' => $requestId, 'input_hash' => $hash, 'input_snapshot' => $input, 'query_plan' => $queries, 'status' => 'pending', 'calls' => [], 'sources' => [], 'budget_id' => $budget->id, 'reserved_microusd' => $reserve, 'pricing_snapshot' => $pricing]);
            $p->update(['active_research_run_id' => $run->id, 'version' => $p->version + 1]);
            ProcessMarketingResearch::dispatch($run->id)->afterCommit();
            return $run;
        });
        return $run;
    }

    public function view(string $project, int $actor, string $run): array
    {
        $p = $this->owned($project, $actor);
        $r = MarketingResearchRun::where('project_id', $project)->findOrFail($run);
        return $r->only(['id', 'project_id', 'status', 'input_hash', 'query_plan', 'sources', 'analysis', 'error_code', 'reserved_microusd', 'reported_cost_microusd', 'created_at', 'finished_at']) + ['execution_mode' => $r->pricing_snapshot['execution_mode'], 'input_matches_current' => hash_equals($r->input_hash, $this->hash($this->input($p)))];
    }

    public function interrupted(string $project, int $actor, int $version, string $run): void
    {
        $this->owned($project, $actor);
        DB::transaction(function () use ($project, $version, $run) {
            $p = MarketingProject::lockForUpdate()->findOrFail($project);
            $r = MarketingResearchRun::where('project_id', $project)->lockForUpdate()->findOrFail($run);
            abort_unless($p->version === $version && $p->active_research_run_id === $run && $r->created_at->lte(now()->subMinutes(10)), 409, 'The request is still active or this version has changed.');
            $r->update(['status' => 'unknown', 'error_code' => 'interrupted_outcome_unknown', 'finished_at' => now()]);
            $p->update(['active_research_run_id' => null, 'version' => $p->version + 1]);
        });
    }
}
