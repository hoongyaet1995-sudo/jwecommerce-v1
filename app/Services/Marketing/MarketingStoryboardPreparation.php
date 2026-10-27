<?php

namespace App\Services\Marketing;

use App\Jobs\ProcessMarketingStoryboardPreparation;
use App\Models\MarketingPreparationAuthorization as Authorization;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardTask;
use App\Models\MarketingStoryboardWorkspace;
use Illuminate\Support\Facades\DB;

class MarketingStoryboardPreparation
{
    public function prepare(string $changeId, string $authorizationId, array $policy = []): array
    {
        app(MarketingGenerationQuotes::class)->activateAuthorization($authorizationId);
        abort_unless((app()->environment('testing') && app()->bound('marketing.storyboard.image_transport')) || (app(MarketingStoryboardProvider::class)->allowed('image', $changeId) && app(MarketingStoryboardProvider::class)->allowed('qa', $changeId)), 423, '真实图片准备尚未授权，未发出生成请求。');
        $a = Authorization::findOrFail($authorizationId);
        abort_unless($a->project_id === $changeId, 403);
        $w = MarketingStoryboardWorkspace::where('project_id', $a->project_id)->firstOrFail();
        abort_unless($w->version === $a->story_version, 409, '故事已变化。');
        $tasks = [];
        foreach ($a->scope as $revision) {
            $r = MarketingSceneRevision::findOrFail($revision);
            $task = MarketingStoryboardTask::firstOrCreate(['authorization_id' => $a->id, 'revision_id' => $r->id], ['project_id' => $a->project_id, 'signature' => $r->signature, 'status' => 'queued', 'result' => $policy ? ['request_policy'=>$policy] : []]);
            $tasks[] = $task->id;
            if ($task->wasRecentlyCreated) {
                ProcessMarketingStoryboardPreparation::dispatch($task->id);
            }
        }

        return ['tasks' => $tasks];
    }

    public function process(string $runId): void
    {
        $task = MarketingStoryboardTask::findOrFail($runId);
        app(MarketingGenerationQuotes::class)->activateAuthorization($task->authorization_id);
        if (! (app()->environment('testing') && app()->bound('marketing.storyboard.image_transport')) && ! (app(MarketingStoryboardProvider::class)->allowed('image', $task->project_id) && app(MarketingStoryboardProvider::class)->allowed('qa', $task->project_id))) {
            $task->update(['status' => 'blocked', 'result' => array_replace($task->result ?? [], ['block_code'=>'worker_authorization_unavailable','message'=>'后台未能承接本次费用授权，未发出生成请求。请核对制作队列配置；不会自动重试。'])]);

            return;
        }
        $claimed = DB::transaction(function () use ($task) {
            $t = MarketingStoryboardTask::lockForUpdate()->findOrFail($task->id);
            if ($t->status !== 'queued') {
                return false;
            }$r = MarketingSceneRevision::findOrFail($t->revision_id);
            if (! MarketingScene::whereKey($r->scene_id)->whereNull('removed_at')->where('current_revision_id', $r->id)->exists()) {
                $t->update(['status' => 'historical']);

                return false;
            }
            $authorization = Authorization::findOrFail($t->authorization_id);
            $work = MarketingStoryboardWorkspace::where('project_id', $t->project_id)->firstOrFail();
            if ($work->version !== $authorization->story_version) {
                $t->update(['status' => 'historical']);

                return false;
            }
            $t->update(['status' => 'requesting']);

            return true;
        });
        if (! $claimed) {
            return;
        }
        if (!$this->approvedPricing($task, 'image') || !$this->approvedPricing($task, 'qa')) {
            $task->update(['status'=>'blocked','result'=>array_replace($task->result??[],['message'=>'模型或费率已变化，未继续调用；请重新核价确认。'])]);
            return;
        }
        if(!$this->current($task)){$task->update(['status'=>'historical']);return;}
        $fixture = app()->environment('testing') && app()->bound('marketing.storyboard.image_transport');
        $maxRepairs=(int)($task->result['request_policy']['max_repairs']??config('marketing.storyboard.max_repairs',1));
        $budget = app(MarketingPreparationBudget::class);
        $revision = MarketingSceneRevision::findOrFail($task->revision_id);
        $effective = app(\App\Services\Marketing\MarketingStoryboardWorkspace::class)->effectiveContent($revision);
        $frames = $effective['frames'];
        $reused = app(MarketingStoryboardPackage::class)->ready($effective);
        $task->update(['result' => array_replace($task->result ?? [], ['frames' => $frames, 'image_reused' => $reused])]);
        foreach ($frames as $role => $frame) {
            if(isset($effective['generation_roles'])&&!in_array($role,$effective['generation_roles'],true))continue;
            if (! $frame['required'] || (! empty($frame['asset']) && in_array($frame['readiness'], ['qa_passed', 'employee_declared_local_validated'], true))) {
                continue;
            }
            $firstAttempt = 0;
            if (! empty($frame['asset']) && $frame['readiness'] === 'pending_qa') {
                try {
                    $this->finalize($task->id, ['asset' => $frame['asset'], 'image_reused' => true], $role);
                    $task->refresh();
                    $status = $task->result['last_qa']['status'] ?? '';
                    if ($status === 'pass') {
                        $this->checkpoint($task);

                        return;
                    }if ($status !== 'repair') {
                        return;
                    }if($maxRepairs===0)return;
                    $firstAttempt = 1;
                } catch (\Throwable $e) {
                    $task->refresh();
                    $task->update(['status' => 'unknown']);
                    throw $e;
                }
            }
            for ($attempt = $firstAttempt; $attempt <= $maxRepairs; $attempt++) {
                if (! MarketingScene::whereKey($revision->scene_id)->whereNull('removed_at')->where('current_revision_id', $revision->id)->exists()) {
                    $task->update(['status' => 'historical']);

                    return;
                }
                $claim = 'image:'.$task->id.':'.$attempt.($role === 'start_frame' ? '' : ':'.$role);
                try {
                    if ($fixture) {
                        abort_unless($budget->reserve($task->authorization_id, $claim, 100), 409, '已领取的请求不会重发。');
                    }
                    $task->update(['status' => 'requesting']);
                    $result = app(MarketingStoryboardImages::class)->generate($task->id, $attempt, $role);
                    if ($fixture) {
                        $budget->settle($task->authorization_id, $claim, $result['actual_microusd'] ?? null);
                    }
                    $this->finalize($task->id, $result + ['attempt' => $attempt], $role);
                    $task->refresh();
                    if ($task->status === 'historical') {
                        return;
                    }
                    $qa = $task->result['last_qa'] ?? [];
                    if (($qa['status'] ?? '') === 'pass') {
                        break;
                    }
                    if (($qa['status'] ?? '') !== 'repair' || $attempt === $maxRepairs) {
                        return;
                    }
                } catch (\Throwable $e) {
                    $a = Authorization::find($task->authorization_id);
                    if (isset($a->claims[$claim])) {
                        $budget->settle($a->id, $claim, null);
                    }$task->refresh();
                    $task->update(['status' => 'unknown', 'result' => array_merge($task->result ?? [], ['message' => '图片结果或费用待核对，不会自动重试。'])]);
                    throw $e;
                }
            }
            $this->checkpoint($task);

            return;
        }
        $task->refresh();
        if ($task->status !== 'historical') {
            $this->checkpoint($task);
        }
    }

    private function checkpoint(MarketingStoryboardTask $task): void
    {
        $task->refresh();
        if (in_array($task->status, ['historical', 'unknown', 'blocked'], true)) {
            return;
        }
        $r = MarketingSceneRevision::findOrFail($task->revision_id);
        $c = app(MarketingStoryboardPackage::class)->content($r->content);
        $c['frames'] = $task->result['frames'];
        $selected=$c['generation_roles']??array_keys($c['frames']);
        $remaining=collect($c['frames'])->contains(fn($f,$role)=>in_array($role,$selected,true)&&$f['required']&&(empty($f['asset'])||!in_array($f['readiness'],['qa_passed','employee_declared_local_validated'],true)));
        $task->update(['status' => app(MarketingStoryboardPackage::class)->ready($c) ? 'ready' : ($remaining?'queued':'partial')]);
    }

    private function current(MarketingStoryboardTask $task):bool {
        $r=MarketingSceneRevision::findOrFail($task->revision_id);
        if(!MarketingScene::whereKey($r->scene_id)->whereNull('removed_at')->where('current_revision_id',$r->id)->exists())return false;
        $w=MarketingStoryboardWorkspace::where('project_id',$task->project_id)->firstOrFail();
        $a=Authorization::findOrFail($task->authorization_id);if($w->version!==$a->story_version)return false;
        if(($r->content['presenter_required']??false)&&!empty($w->profile['presenter']['avatar_binding'])){try{app(MarketingAvatarBinding::class)->assertUsable($task->project_id,\App\Models\MarketingProject::findOrFail($task->project_id)->owner_id,$w->profile['presenter']['avatar_binding']);}catch(\Throwable){return false;}}
        return true;
    }
    public function finalize(string $taskId, array $result, string $role = 'start_frame'): void
    {
        $task = MarketingStoryboardTask::findOrFail($taskId);
        $r = MarketingSceneRevision::findOrFail($task->revision_id);
        $ref = app(MarketingStoryboardUpload::class)->reference($task->project_id, (string) ($result['asset']['asset_id'] ?? ''));
        abort_unless($ref['kind'] === 'image' && hash_equals($ref['sha256'], (string) ($result['asset']['sha256'] ?? '')), 409, '生成素材校验失败。');
        $result['asset'] = $ref;
        $current = $this->current($task);
        $scene = app(MarketingStoryboardPackage::class)->content($r->content);
        if ($current && !$this->approvedPricing($task,'qa')) {
            $saved=$task->result??[];$saved['frames'][$role]=array_replace($scene['frames'][$role],['asset'=>$ref,'readiness'=>'pending_qa','generation'=>$result]);
            $saved['message']='图片已返回；检查模型或费用已变化，尚未检查，未继续调用。';
            $task->update(['status'=>'blocked','result'=>$saved]);return;
        }
        $qa = $current ? app(MarketingStoryboardQa::class)->review($scene, $result, [], $taskId, $role) : ['status' => 'uncertain', 'evidence' => 'historical result, no new QA operation'];
        DB::transaction(function () use ($taskId, $r, $result, $qa, $role, $scene) {
            $task = MarketingStoryboardTask::lockForUpdate()->findOrFail($taskId);
            $current = $this->current($task);
            $saved = $task->result ?? [];
            $saved['frames'][$role] = array_replace($scene['frames'][$role], ['asset' => $result['asset'], 'readiness' => $qa['status'] === 'pass' ? 'qa_passed' : 'pending', 'qa' => $qa, 'generation' => $result]);
            $saved['last_qa'] = $qa;
            if ($role === 'start_frame') {
                $saved = array_replace($saved, $result + ['qa' => $qa]);
            }$task->update(['status' => ! $current ? 'historical' : ($qa['status'] === 'pass' ? (app(MarketingStoryboardPackage::class)->ready(array_replace($scene, ['frames' => $saved['frames']])) ? 'ready' : 'requesting') : 'blocked'), 'result' => $saved]);
        });
    }

    private function approvedPricing(MarketingStoryboardTask $task,string $role): bool
    {
        $approved=$task->result['request_policy']['pricing'][$role]??null;
        if(!$approved)return true; // Existing flows keep their original authorization contract.
        try{
            $cfg=app(MarketingStoryboardProvider::class)->role($role);
            $connection=$role==='image'?app(MarketingMaterialImageClient::class)->configuration():app(MarketingMaterialVisionClient::class)->configuration();
            return $approved['config']===$cfg&&$approved['connection']===$connection;
        }catch(\Throwable){return false;}
    }
}
