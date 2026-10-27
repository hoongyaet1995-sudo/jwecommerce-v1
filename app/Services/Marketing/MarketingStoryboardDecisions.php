<?php

namespace App\Services\Marketing;

use App\Models\AiConnection;
use App\Models\MarketingAsset;
use App\Models\MarketingProject;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingStoryboardSemanticRun;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** Versioned employee decisions. No image generation or production is dispatched here. */
class MarketingStoryboardDecisions
{
    private function context(string $project, int $owner, int $version): array
    {
        $s = app(MarketingStoryboardWorkspace::class)->snapshot($project, $owner);
        abort_unless($s['source_fresh'] && $s['story_version'] === $version, 409, '故事已更新，请刷新后重新安排。');
        abort_if(($s['preparation_progress']['active'] ?? 0) > 0 || ($s['preparation_progress']['unknown'] ?? 0) > 0, 409, '图片任务仍在处理或费用待核对，请完成后再确认素材。');
        $p = MarketingProject::findOrFail($project);
        $s['product_reference_catalog'] = array_map(fn ($i, $url) => ['index' => $i, 'source' => $url, 'sha256' => ($path = app(MarketingMaterialPackage::class)->catalogPath($p, $i)) ? hash_file('sha256', $path) : null], array_keys($p->snapshot['images'] ?? []), array_values($p->snapshot['images'] ?? []));

        return $s;
    }

    private function fingerprint(array $s): string
    {
        return hash('sha256', json_encode([$s['story_version'], $s['scenes'], $s['profile'], $s['product_reference_catalog'], app(MarketingVideoRoutes::class)->catalog(), AiConnection::where('provider', 'openai')->value('version')], JSON_THROW_ON_ERROR));
    }

    public function preview(string $project, int $owner, string $request, int $version): array
    {
        $s = $this->context($project, $owner, $version);
        $provider = app(MarketingStoryboardProvider::class);
        // Even a new textual analysis must use the existing explicit project budget grant.
        abort_unless($provider->allowed('semantic', $project), 423, '重新安排需要一次文字分析，请先确认本次模型与预算。');
        $cfg = $provider->role('semantic');
        app(MarketingStoryboardOpenAi::class)->connection('semantic');
        $fingerprint = $this->fingerprint($s);
        $key = hash('sha256', json_encode(['material-plan-v1', $project, $owner, $fingerprint, $cfg], JSON_THROW_ON_ERROR));
        $cap = (int) config('marketing.storyboard.live_grant.max_total_microusd', 0);
        abort_unless($cap > 0 && $cap <= 100000000, 422, '本次文字分析预算无效，未发送请求。');
        $run = DB::transaction(function () use ($project, $owner, $version, $key, $fingerprint) {
            app(MarketingStoryboardWorkspace::class)->project($project, $owner, true);
            abort_unless(hash_equals($fingerprint, $this->fingerprint($this->context($project, $owner, $version))), 409, '素材已变化，请刷新。');
            $old = MarketingStoryboardSemanticRun::find($key);
            if ($old) {
                abort_unless(in_array($old->status, ['completed', 'blocked'], true), 409, '上次安排尚未完成或结果未知，不会自动重试。');
                if ($old->status === 'blocked') {
                    $old->update(['status' => 'requesting']);
                }

return $old;
            }

            return MarketingStoryboardSemanticRun::create(['id' => $key, 'status' => 'requesting']);
        });
        if ($run->status === 'completed') {
            return $run->result;
        }
        try {
            $auth = app(MarketingPreparationBudget::class)->authorize($project, $owner, $request, $version, array_column($s['scenes'], 'revision_id'), $cap);
            $result = app(MarketingStoryboardOpenAi::class)->materialPlan($project, $auth['id'], $s + ['video_capabilities' => app(MarketingVideoRoutes::class)->catalog()]);
            $draft = $result['answer']['plans'] ?? [];
            abort_unless(is_array($draft) && count($draft) === count($s['scenes']) && count(array_unique(array_column($draft, 'scene_id'))) === count($s['scenes']) && ! array_diff(array_column($draft, 'scene_id'), array_column($s['scenes'], 'scene_id')), 422, 'AI素材安排未完整覆盖当前分镜。');
            $draft = collect($draft)->keyBy('scene_id');
            $plans = [];
            foreach ($s['scenes'] as $i => $scene) {
                $plan = $draft[$scene['scene_id']]['material_plan'] ?? null;
                abort_unless(is_array($plan), 422, 'AI未返回素材安排。');
                $validated = app(MarketingStoryboardContract::class)->content(array_replace($this->design($scene), ['material_plan' => $plan]));
                $plans[] = ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'ordinal' => $i + 1, 'before' => app(MarketingVideoRoutes::class)->intent($scene), 'after' => $validated['material_plan']];
            }
            $result = ['key' => $key, 'project_id' => $project, 'owner_id' => $owner, 'story_version' => $version, 'fingerprint' => $fingerprint, 'plans' => $plans];
            $run->update(['status' => 'completed', 'result' => $result]);

            return $result;
        } catch (\Throwable $e) {
            // Provider claims are durable before transport. No claim means this request never left the app.
            $claimed = isset($auth) && MarketingStoryboardProviderCall::where('authorization_id', $auth['id'])->where('role', 'semantic')->exists();
            $run->update(['status' => $claimed ? 'unknown' : 'blocked']);
            throw $e;
        }
    }

    public function planningInfo(string $project): array
    {
        try {
            $allowed = app(MarketingGenerationQuotes::class)->enabled()||app(MarketingStoryboardProvider::class)->allowed('semantic', $project);
            [$connection,$cfg] = app(MarketingStoryboardOpenAi::class)->connection('semantic');

            return ['model' => $cfg['model'], 'cap_microusd' => $cfg['cap_microusd'], 'allowed' => $allowed];
        } catch (\Throwable) {
            return ['allowed' => false, 'message' => '文字分析模型或账户费率尚未核验，未开始调用。'];
        }
    }

    private function design(array $c): array
    {
        $c = array_intersect_key($c, array_flip(['seconds', 'message', 'visual', 'voiceover', 'subtitle', 'presenter_required', 'fact_refs', 'director', 'scene_design', 'material_plan', 'frames']));
        foreach ($c['frames'] as &$frame) {
            $frame = array_intersect_key($frame, array_flip(['required', 'instruction', 'state', 'person_presence']));
        }unset($frame);

        return $c;
    }

    private function revise(MarketingScene $row, array $content, array $profile): void
    {
        foreach ($content['frames'] as &$frame) unset($frame['existing_asset_review_required'],$frame['adoption_technical_issue'],$frame['qa_override_required'],$frame['adoption_issue'],$frame['continuity_ack_required'],$frame['framing_sync_preview']);
        unset($frame);
        $r = MarketingSceneRevision::create(['scene_id' => $row->id, 'parent_id' => $row->current_revision_id, 'content' => $content, 'signature' => app(MarketingStoryboardContract::class)->signature($content, $profile)]);
        $row->update(['current_revision_id' => $r->id]);
    }

    public function applyPlan(string $project, int $owner, string $request, int $version, string $key): array
    {
        return app(MarketingStoryboardWorkspace::class)->transact($project, $owner, $request, ['material-plan', $version, $key], function () use ($project, $owner, $version, $key) {
            $s = $this->context($project, $owner, $version);
            $run = MarketingStoryboardSemanticRun::findOrFail($key);
            $result = $run->result;
            abort_unless($run->status === 'completed' && ($result['project_id'] ?? null) === $project && ($result['owner_id'] ?? null) === $owner && hash_equals($result['fingerprint'] ?? '', $this->fingerprint($s)), 409, '安排预览已过期，请重新安排。');
            $w = Workspace::where('project_id', $project)->lockForUpdate()->firstOrFail();
            foreach ($s['scenes'] as $i => $scene) {
                $plan = $result['plans'][$i];
                abort_unless($plan['revision_id'] === $scene['revision_id'], 409);
                $scene['material_plan'] = $plan['after'];
                $scene['material_plan_decision'] = ['actor_id' => $owner, 'at' => now()->toIso8601String(), 'preview_key' => $key, 'origin' => 'ai_preview_employee_applied'];
                $scene = app(MarketingStoryboardPackage::class)->content($scene);
                $this->revise(MarketingScene::where('project_id', $project)->findOrFail($scene['scene_id']), $scene, $w->profile);
            }
            $w->update(['version' => $w->version + 1, 'last_confirmation' => null]);
        });
    }

    public function unassessedContinuity(array $frame): bool
    {
        $e = $frame['qa']['evidence'] ?? [];
        return ($e['checks']['scene_continuity'] ?? '') === 'uncertain'
            && preg_match('/缺少.{0,30}参考|未.{0,12}(检查|核对|评估)|not.assessed|missing.{0,30}reference/iu', json_encode($e, JSON_UNESCAPED_UNICODE)) === 1;
    }

    /** Replace framing constraints only; identity, actions, copy and timing remain saved verbatim. */
    public function framingSync(array $scene, string $role): array
    {
        $old = $scene['director']['framing'];
        $replace = function (string $text) use ($old): string {
            if ($old !== '') $text = str_replace($old, '采用已确认画面的构图', $text);
            return preg_replace('/胸口以上|胸部以上|腰部以上|中近景|中远景|中景|近景|远景|半身(?:构图)?|全身(?:构图)?|(?:人物|KOL\s*A|主持人)位于画面(?:中央)?(?:偏左|偏右|左侧|右侧|中央)/iu', '构图沿用已确认画面', $text);
        };
        $scene['visual'] = $replace($scene['visual']);
        foreach (['subject','framing','motion','continuity','start_state','end_state'] as $key) $scene['director'][$key] = $replace($scene['director'][$key]);
        $scene['director']['framing'] = '采用用户已确认图片的构图，以该图片为准';
        if (isset($scene['scene_design']['person_placement'])) $scene['scene_design']['person_placement'] = $replace($scene['scene_design']['person_placement']);
        // Only the framing phrases inside actions change; action verbs and beat times are preserved.
        foreach($scene['director']['beats'] as &$beat)foreach(['action','description'] as $key)if(isset($beat[$key]))$beat[$key]=$replace($beat[$key]);unset($beat);
        $scene['frames'][$role]['instruction'] = $replace($scene['frames'][$role]['instruction']);
        if (isset($scene['frames'][$role]['state'])) $scene['frames'][$role]['state'] = $replace($scene['frames'][$role]['state']);
        return $scene;
    }

    public function reviewAsset(array $frame):?array
    {
        return $frame['candidate_asset']??((($frame['readiness']??'')==='pending_sync')?($frame['asset']??null):null);
    }

    public function adoptionIssue(string $project, array $scene, string $role, bool $acknowledged = false, bool $acceptAiFindings = false): ?string
    {
        $f = $scene['frames'][$role] ?? [];
        $candidate = $this->reviewAsset($f);
        if (($f['readiness'] ?? '') === 'invalid') {
            return '人物或商品参考已改变，请重新核对候选。';
        }
        if (! $candidate) {
            return '候选图片不可用，请重新选择素材。';
        }
        try {
            $ref = app(MarketingStoryboardUpload::class)->reference($project, $candidate['asset_id']);
            $a = MarketingAsset::findOrFail($ref['asset_id']);
            $info = @getimagesize(Storage::disk('local')->path($a->path));
            abort_unless($ref['kind'] === 'image' && hash_equals($candidate['sha256'], $ref['sha256']) && $info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) && $info[0] * $info[1] <= 40000000 && $a->size <= 10485760, 422);
        } catch (\Throwable) {
            return '图片文件不可用或已改变，请重新选择素材。';
        }
        // A human can accept visual quality findings, never an unusable or changed file.
        if ($acceptAiFindings) return null;
        $checks = $f['qa']['evidence']['checks'] ?? [];
        foreach (MarketingMaterialVisionClient::CHECKS as $check) {
            if ($check === 'scene_continuity' && $this->unassessedContinuity($f) && $acknowledged) continue;
            if ($check === 'composition') {
                if (! in_array($checks[$check] ?? null, ['pass', 'fail'], true)) {
                    return '构图检查不完整，请先调整或重新核对。';
                }

continue;
            }
            if (($checks[$check] ?? null) !== 'pass') {
                return '人物、商品、动作或连续性仍需核对，请通过“告诉 AI 怎么改”调整。';
            }
        }

        return null;
    }

    public function adopt(string $project, int $owner, string $request, int $version, array $command): array
    {
        Validator::make($command, ['scene_id' => 'required|uuid', 'revision_id' => 'required|uuid', 'role' => 'required|in:start_frame,end_frame,extra_image', 'sha256' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'sync_framing' => 'required|boolean', 'accept_unassessed_continuity'=>'sometimes|boolean', 'accept_ai_findings'=>'sometimes|boolean'])->validate();

        return app(MarketingStoryboardWorkspace::class)->transact($project, $owner, $request, ['adopt-candidate', $version, $command], function () use ($project, $owner, $request, $version, $command) {
            $s = $this->context($project, $owner, $version);
            $scene = collect($s['scenes'])->firstWhere('scene_id', $command['scene_id']);
            abort_unless($scene && $scene['revision_id'] === $command['revision_id'], 409, '候选所属分镜已变化，请刷新。');
            $role = $command['role'];
            $acceptAiFindings = (bool)($command['accept_ai_findings'] ?? false);
            $issue = $this->adoptionIssue($project, $scene, $role, (bool)($command['accept_unassessed_continuity'] ?? false), $acceptAiFindings);
            abort_if($issue !== null, 422, $issue ?? '');
            $f = &$scene['frames'][$role];
            $reviewAsset=$this->reviewAsset($f);$reuse=empty($f['candidate_asset'])&&($f['readiness']??'')==='pending_sync';
            abort_unless($reviewAsset&&hash_equals($command['sha256'], $reviewAsset['sha256']), 409, '候选图片已变化。');
            $oldFraming = $scene['director']['framing'];
            $sync = $command['sync_framing'];
            $difference = ($f['qa']['evidence']['checks']['composition'] ?? '') === 'fail';
            $f['asset'] = $reviewAsset;
            if ($sync || ! $difference) {
                unset($f['candidate_asset']);
            }
            $f['readiness'] = $difference && ! $sync ? 'pending_sync' : 'employee_declared_local_validated';
            $f['employee_acceptance'] = ['actor_id' => $owner, 'at' => now()->toIso8601String(), 'request_id' => $request, 'revision_id' => $command['revision_id'], 'sha256' => $command['sha256'], 'qa' => $f['qa']??null, 'sync_framing' => $sync, 'previous_framing' => $oldFraming];
            $f['employee_acceptance']['unassessed_continuity_accepted'] = $this->unassessedContinuity($f) && ($command['accept_unassessed_continuity'] ?? false);
            $f['employee_acceptance']['ai_findings_accepted'] = $acceptAiFindings;
            $f['employee_acceptance']['reused_existing_image'] = $reuse;
            if ($sync && $difference) {
                unset($f);
                $scene = $this->framingSync($scene, $role);
                foreach ($scene['frames'] as $other => &$otherFrame) {
                    if ($other !== $role && ! empty($otherFrame['asset'])) {
                        $otherFrame['readiness'] = $otherFrame['required'] ? 'pending_sync' : $otherFrame['readiness'];
                    }
                }unset($otherFrame);
            }
            unset($f);
            $scene['asset'] = $scene['frames']['start_frame']['asset'];
            $scene['readiness'] = $scene['frames']['start_frame']['readiness'];
            $w = Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();
            $this->revise(MarketingScene::where('project_id',$project)->findOrFail($scene['scene_id']),$scene,$w->profile);
            $w->update(['version' => $w->version + 1, 'last_confirmation' => null]);
        });
    }

    public function syncAccepted(string $project,int $owner,string $request,int $version,array $command): array
    {
        Validator::make($command,['scene_id'=>'required|uuid','revision_id'=>'required|uuid','role'=>'required|in:start_frame,end_frame,extra_image','sha256'=>'required|regex:/^[a-f0-9]{64}$/D'])->validate();
        return app(MarketingStoryboardWorkspace::class)->transact($project,$owner,$request,['sync-accepted-framing',$version,$command],function()use($project,$owner,$version,$command){
            $s=$this->context($project,$owner,$version);$scene=collect($s['scenes'])->firstWhere('scene_id',$command['scene_id']);
            abort_unless($scene&&$scene['revision_id']===$command['revision_id'],409,'画面所属分镜已变化。');
            $role=$command['role'];$f=$scene['frames'][$role];
            abort_unless(($f['readiness']??'')==='employee_declared_local_validated'&&!empty($f['employee_acceptance']['sync_framing'])&&hash_equals($f['employee_acceptance']['sha256']??'',$command['sha256'])&&hash_equals($f['asset']['sha256']??'',$command['sha256']),422,'请先核对并采用当前图片。');
            $fresh=app(MarketingStoryboardUpload::class)->reference($project,$f['asset']['asset_id']);abort_unless(hash_equals($fresh['sha256'],$command['sha256']),409,'图片文件已变化。');
            $scene=$this->framingSync($scene,$role);$scene['frames'][$role]['employee_acceptance']['framing_resynchronized_at']=now()->toIso8601String();
            foreach($scene['frames'] as $r=>&$other)if($r!==$role&&$other['required']&&!empty($other['asset']))$other['readiness']='pending_sync';unset($other);
            $w=Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();$this->revise(MarketingScene::findOrFail($scene['scene_id']),$scene,$w->profile);$w->update(['version'=>$w->version+1,'last_confirmation'=>null]);
        });
    }
}
