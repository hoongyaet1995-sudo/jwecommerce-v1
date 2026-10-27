<?php

namespace App\Services\Marketing;

use App\Jobs\ProcessMarketingProduction;
use App\Models\MarketingProduction;
use App\Models\MarketingProject;
use App\Models\MarketingVoiceAsset;
use Illuminate\Support\Facades\DB;

class MarketingProductionRunner
{
    public function start(string $project, int $owner, int $version, string $plan): MarketingProduction
    {
        return DB::transaction(function () use ($project, $owner, $version, $plan) {
            MarketingProject::where('owner_id', $owner)->lockForUpdate()->findOrFail($project);
            $existing = MarketingProduction::where('project_id', $project)->where('plan_id', $plan)->first();
            if ($existing) {
                $localDynamic=\App\Models\MarketingProductionPlan::find($existing->plan_id)?->manifest['execution_profile']??null;
                $renderRetry = $existing->status === 'failed' && in_array($existing->error_code, ['render_failed', 'media_processing_failed', 'render_result_invalid'], true) && ((in_array($localDynamic,['local-only-v1',\App\Services\Marketing\MarketingHybridProduction::PROFILE],true))||($existing->voice_asset_id && MarketingVoiceAsset::whereKey($existing->voice_asset_id)->where('status', 'ready')->exists()));
                if (($existing->status === 'blocked' && $existing->new_reserved_microusd === 0) || $renderRetry) {
                    app(MarketingProductionPreparation::class)->validateForProduction($project, $owner, $version, $plan);
                    $existing->update(['status' => 'queued', 'error_code' => null, 'started_at' => null, 'finished_at' => null]);
                    ProcessMarketingProduction::dispatch($existing->id);
                }

                return $existing;
            }
            app(MarketingProductionPreparation::class)->validateForProduction($project, $owner, $version, $plan);
            $task = MarketingProduction::create(['project_id' => $project, 'plan_id' => $plan, 'owner_id' => $owner, 'status' => 'queued']);
            // Same database transaction: insert the task and its isolated job atomically.
            ProcessMarketingProduction::dispatch($task->id);

            return $task;
        });
    }
}
