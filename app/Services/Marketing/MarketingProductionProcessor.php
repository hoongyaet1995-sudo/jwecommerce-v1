<?php

namespace App\Services\Marketing;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProject;
use App\Models\MarketingVoiceAsset;
use Illuminate\Support\Facades\DB;

class MarketingProductionProcessor
{
    public function process(string $id): void
    {
        try {
            $pending=MarketingProduction::findOrFail($id);
            if($pending->status==='external_check_queued'){if((MarketingProductionPlan::findOrFail($pending->plan_id)->manifest['execution_profile']??null)===MarketingHybridProduction::PROFILE)app(MarketingHybridProduction::class)->processCheck($id);else app(MarketingNativeProduction::class)->processCheck($id);return;}
            $plan = DB::transaction(function () use ($id) {
                $first = MarketingProduction::findOrFail($id);
                MarketingProject::whereKey($first->project_id)->lockForUpdate()->firstOrFail();
                $task = MarketingProduction::lockForUpdate()->findOrFail($id);
                if ($task->status !== 'queued') {
                    return null;
                }
                $plan = MarketingProductionPlan::findOrFail($task->plan_id);
                try {
                    app(MarketingProductionPreparation::class)->validateForProduction($task->project_id, $task->owner_id, $plan->project_version, $plan->id);
                } catch (\Throwable) {
                    throw new MarketingProductionFailure('approval_no_longer_valid', 'blocked');
                }
                $task->update(['status' => 'preparing', 'started_at' => now()]);

                return $plan;
            });
            if (! $plan) {
                return;
            }
            $task = MarketingProduction::findOrFail($id);
            if(($plan->manifest['recipe']??null)===MarketingNativePresenter::RECIPE){app(MarketingNativeProduction::class)->submit($task,$plan);return;}
            if(($plan->manifest['execution_profile']??null)===MarketingHybridProduction::PROFILE){app(MarketingHybridProduction::class)->process($id);return;}
            if(($plan->manifest['recipe']??null)===MarketingToolPlanning::RECIPE){app(MarketingProductionSteps::class)->process($id);return;}
            app(MarketingVideoRenderer::class)->assertReady();
            $workspace = app(MarketingProductionWorkspace::class);
            $directory = $workspace->stage($task, $plan);
            $voice = null;
            if ($plan->manifest['audio']['requests']) {
                $voice = app(MarketingVoiceLibrary::class)->obtain($task, $plan);
                $workspace->stageVoice($directory, $voice);
            }
            $task->refresh();
            if (! MarketingProduction::whereKey($id)->whereIn('status', ['preparing', 'audio_requesting'])->update(['status' => 'rendering'])) {
                return;
            }
            $result = app(MarketingVideoRenderer::class)->render($task, $plan, $directory);
            MarketingProduction::whereKey($id)->where('status', 'rendering')->update(['status' => 'completed', 'output_path' => 'marketing-productions/'.$id.'/output.mp4', 'output_sha256' => $result['sha256'], 'result' => array_replace($task->result??[],$result,['voice_source' => $voice?->source, 'voice_reused' => $voice !== null && $task->new_reserved_microusd === 0 && !($task->result['voice_request_claimed']??false), 'voice_usage' => $voice?->usage, 'publication_authorized' => false]), 'finished_at' => now()]);
        } catch (MarketingProductionFailure $error) {
            MarketingProduction::whereKey($id)->whereNotIn('status', ['completed', 'unknown'])->update(['status' => $error->state, 'error_code' => $error->reason, 'finished_at' => now()]);
        } catch (\Throwable) {
            $this->interrupted($id);
        }
    }

    public function interrupted(string $id): void
    {
        DB::transaction(function () use ($id) {
            $task = MarketingProduction::lockForUpdate()->find($id);
            if (! $task || in_array($task->status, ['completed', 'failed', 'blocked', 'unknown'], true)) {
                return;
            }
            if ($task->voice_asset_id) {
                MarketingVoiceAsset::whereKey($task->voice_asset_id)->where('status', 'requesting')->update(['status' => 'unknown']);
            }
            if((\App\Models\MarketingProductionPlan::find($task->plan_id)?->manifest['recipe']??null)===MarketingToolPlanning::RECIPE)\App\Models\MarketingProductionStep::where('production_id',$id)->whereIn('status',['running','external_submitting'])->update(['status'=>'unknown','error_code'=>'worker_interrupted_outcome_unknown']);
            $task->update(['status' => 'unknown', 'error_code' => 'worker_interrupted_outcome_unknown', 'finished_at' => now()]);
        });
    }
}
