<?php

namespace App\Services\Marketing;

use App\Models\MarketingPreparationAuthorization as Authorization;
use App\Models\MarketingProject;
use App\Models\MarketingStoryboardProviderCall as Call;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Every paid operation commits its claim BEFORE HTTP. No live grants are provided by default. */
class MarketingStoryboardProvider
{
    public function allowed(string $role, string $project): bool
    {
        if(app(MarketingGenerationQuotes::class)->enabled())return app(MarketingGenerationQuotes::class)->activateProject($project)&&in_array($role,config('marketing.storyboard.live_grant.roles',[]),true);
        $batch=config('marketing.storyboard.live_grant.mode')==='approved_batch';
        app(MarketingStoryboardManualTesting::class)->activate($project);
        if(in_array($role,['upload','video'],true))app(MarketingUploadCheckpoint::class)->activateGrant($project);
        app(MarketingEconomyTrialGrant::class)->activateProject($project);
        if($batch||in_array($role,['upload','video'],true))app(MarketingStoryboardBatchApproval::class)->activateProject($project);
        $g = config('marketing.storyboard.live_grant', []);

        return config('marketing.storyboard.live_enabled', false) && ($g['project_id'] ?? '') === $project && in_array($role, $g['roles'] ?? [], true);
    }

    public function role(string $role, ?string $model = null, ?int $seconds = null): array
    {
        $c = config('marketing.storyboard.roles.'.$role, []);
        if($role==='video'&&$model!==null&&($c['model']??null)!==$model){$models=config('marketing.storyboard.video_models',[]);$c=$models[$model]??[];}
        if($model!==null)abort_unless(($c['model']??null)===$model,423,'指定模型的账户费率尚未核验。');
        if($role==='video'&&$seconds!==null&&isset($c['duration_caps'])){abort_unless(isset($c['duration_caps'][$seconds]),423,'本段时长尚未核价。');$c['cap_microusd']=$c['duration_caps'][$seconds];}
        abort_unless(is_array($c) && is_int($c['cap_microusd'] ?? null) && $c['cap_microusd'] > 0 && ! empty($c['model']) && ! empty($c['version']) && ! empty($c['pricing_verified_at']), 423, '请先确认本次角色模型、费用与调用上限。');

        if($role==='image'&&$c['model']==='gpt-image-2')app(MarketingImagePricing::class)->assertStandard($c);
        return $c;
    }

    public function call(string $role, string $project, string $authorization, array $payload, callable $transport, bool $deferred = false): array
    {
        abort_unless(DB::transactionLevel() === 0, 409, '供应商调用不能放在项目长事务中。');
        app(MarketingGenerationQuotes::class)->activateAuthorization($authorization);
        abort_unless($this->allowed($role,$project),423,'当前项目未开启此类AI调用。');
        $cfg = $this->role($role,$role==='video'?($payload['video']['model']??null):null,$role==='video'?($payload['video']['duration']??null):null);
        if($role==='video'&&isset($payload['pricing']))abort_unless($cfg===$payload['pricing'],409,'模型费用快照已变化，未发送请求。');
        if($cfg['enforce_request_bound']??false){$bound=$payload['request_upper_bound_microusd']??null;abort_unless(is_int($bound)&&$bound>0&&$bound<=$cfg['cap_microusd'],423,'请求费用上界未知或超过单次授权，未发送供应商请求。');}
        $g = config('marketing.storyboard.live_grant', []);
        $manual=app()->environment('local')&&($g['mode']??'')==='manual_local';
        abort_unless($this->allowed($role, $project) && ! empty($g['reference']) && ($manual||(!empty($g['expires_at'])&&Carbon::parse($g['expires_at'])->isFuture())) && is_int($g['max_total_microusd'] ?? null) && is_int($g['max_calls'] ?? null) && $g['max_calls'] > 0, 423, '真实API调用尚未获得本次范围与预算批准。');
        $key = hash('sha256', json_encode([$project, $role, $cfg, $payload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $record = DB::transaction(function () use ($project, $authorization, $payload, $role, $key, $cfg, $g, $deferred,$manual) {
            $service = app(MarketingStoryboardWorkspace::class);
            $p = $service->project($project, (int) ($g['owner_id'] ?? 0), true);
            $customer=($g['mode']??'')==='customer_quote';
            $ownerQuery=User::whereKey($p->owner_id)->where('is_admin',true);
            abort_unless(($customer?$ownerQuery:$ownerQuery->where('is_super_admin',true))->exists(),403,'当前管理员权限已变化，未继续供应商调用。');
            $a = Authorization::where('project_id', $project)->lockForUpdate()->findOrFail($authorization);
            if($customer)abort_unless($a->billing_quote_id===($g['quote_id']??null),409,'调用与本次费用确认不一致。');
            $s = $service->snapshot($project, $p->owner_id);
            abort_unless($s['source_fresh'] && $s['story_version'] === $a->story_version, 409, '本次授权所用故事已改变。');
            $ids = $payload['revision_ids'] ?? [$payload['revision_id'] ?? ''];
            abort_unless($ids && count(array_diff($ids, $a->scope)) === 0 && count(array_diff($ids, array_column($s['scenes'], 'revision_id'))) === 0, 409, '供应商请求超出授权范围。');
            $old = Call::find($key);
            if ($old) {
                abort_unless(($old->status === 'completed' || ($deferred && $old->status === 'external_pending')), 409, '上次供应商结果或费用未知，不会自动重试。');

                return $old;
            }
            abort_if(Call::where('project_id', $project)->where('status', 'unknown')->exists(), 409, '当前项目有供应商结果或费用待核对，已暂停后续收费调用。');
            $calls = Call::where('project_id', $project)->where('grant_reference', $g['reference'])->get();
            $reserved = $calls->sum(fn ($c) => $c->status === 'completed' ? $c->actual_microusd : $c->cap_microusd);
            abort_unless($manual||($calls->count() < $g['max_calls'] && $reserved + $cfg['cap_microusd'] <= $g['max_total_microusd']), 409, '本次批准的总额度或调用次数不足。');
            abort_unless(app(MarketingPreparationBudget::class)->reserve($a->id, 'provider:'.$key, $cfg['cap_microusd']), 409, '请求已领取。');

            return Call::create(['id' => $key, 'project_id' => $project, 'authorization_id' => $a->id, 'role' => $role, 'grant_reference' => $g['reference'], 'cap_microusd' => $cfg['cap_microusd'], 'status' => 'requesting', 'input' => $payload]);
        });
        if ($record->status === 'completed' || ($deferred && $record->status === 'external_pending')) {
            return $record->result;
        }
        try {
            $result = $transport($record);
            abort_unless(is_array($result), 409, '供应商响应无法核对。');
            if ($deferred) {
                abort_unless($role === 'video' && preg_match('/^[a-zA-Z0-9_-]{1,160}$/D', $result['id'] ?? ''), 409, '视频任务号无效，结果待核对。');
                $result['provider_call_id'] = $record->id;
                $record->update(['status' => 'external_pending', 'result' => $result]);

                return $record->fresh()->result;
            }
            $actual = $result['actual_microusd'] ?? null;
            $record->update(['result' => $result]);
            if (! is_int($actual) || $actual < 0 || $actual > $record->cap_microusd) {
                app(MarketingPreparationBudget::class)->settle($record->authorization_id, 'provider:'.$key, null);
                $record->update(['status' => 'unknown']);
                abort(409, '供应商结果已保留，实际费用待核对，不会继续调用。');
            }app(MarketingPreparationBudget::class)->settle($record->authorization_id, 'provider:'.$key, $actual);
            $record->update(['status' => 'completed', 'actual_microusd' => $actual]);

            return $record->fresh()->result;
        } catch (\Throwable $e) {
            $record->refresh();
            if ($record->status === 'requesting') {
                app(MarketingPreparationBudget::class)->settle($record->authorization_id, 'provider:'.$key, null);
                $record->update(['status' => 'unknown']);
            }throw $e;
        }
    }

    public function beginVideo(string $project, string $authorization, array $payload, callable $transport): array
    {
        return $this->call('video', $project, $authorization, $payload, $transport, true);
    }

    /** Settles a known task even if the employee has since changed the story; adoption is separate. */
    public function completeVideo(string $id, string $providerTask, array $result): void
    {
        $valid = DB::transaction(function () use ($id, $providerTask, $result) {
            $first = Call::findOrFail($id);
            MarketingProject::whereKey($first->project_id)->lockForUpdate()->firstOrFail();
            $call = Call::lockForUpdate()->findOrFail($id);
            abort_unless($call->role === 'video' && ($call->result['id'] ?? '') === $providerTask, 409, '供应商任务绑定已改变。');
            if ($call->status === 'completed') {
                return true;
            }abort_unless($call->status === 'external_pending', 409, '任务状态不可结算。');
            $actual = $result['actual_microusd'] ?? null;
            $known = is_int($actual) && $actual >= 0 && $actual <= $call->cap_microusd;
            app(MarketingPreparationBudget::class)->settle($call->authorization_id, 'provider:'.$call->id, $known ? $actual : null);
            $call->update(['status' => $known ? 'completed' : 'unknown', 'actual_microusd' => $known ? $actual : null, 'result' => array_replace($call->result ?? [], $result)]);

            return $known;
        });
        abort_unless($valid, 409, '视频结果已保留，实际费用待核对，暂停后续收费调用。');
    }

    /** Freeze interrupted claims only; known async tasks remain queryable. */
    public function interrupted(string $authorization, ?string $revision = null): void
    {
        foreach (Call::where('authorization_id', $authorization)->where('status', 'requesting')->get() as $call) {
            if ($revision !== null && ($call->input['revision_id'] ?? null) !== $revision) {
                continue;
            }
            DB::transaction(function () use ($call) {
                $locked = Call::lockForUpdate()->findOrFail($call->id);
                if ($locked->status !== 'requesting') {
                    return;
                }app(MarketingPreparationBudget::class)->settle($locked->authorization_id, 'provider:'.$locked->id, null);
                $locked->update(['status' => 'unknown']);
            });
        }
    }
}
