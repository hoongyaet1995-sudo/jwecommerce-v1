<?php

namespace App\Services\Marketing;

use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingProject;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardCommand;
use App\Models\MarketingStoryboardTask;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingStoryboardWorkspace
{
    public function project(string $project, int $owner, bool $lock = false): MarketingProject
    {
        $q = MarketingProject::whereKey($project)->where('owner_id', $owner);
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->firstOrFail();
    }

    public function snapshot(string $project, int $owner): array
    {
        $p = $this->project($project, $owner);
        $w = Workspace::where('project_id', $project)->first();
        if (! $w) {
            return ['contract' => 'storyboard-workspace-v1', 'story_id' => null, 'story_version' => 0, 'project_version' => $p->version, 'scenes' => []];
        }
        $rows = MarketingScene::whereIn('id', $w->scene_order)->where('project_id', $project)->get()->keyBy('id');
        $rev = MarketingSceneRevision::whereIn('id', $rows->pluck('current_revision_id'))->get()->keyBy('id');
        $scenes = [];
        foreach ($w->scene_order as $i => $id) {
            $row = $rows[$id];
            $r = $rev[$row->current_revision_id];
            $effective = $this->effectiveContent($r);
            if (! empty($effective['asset'])) {
                try {
                    $fresh = app(MarketingStoryboardUpload::class)->reference($project, $effective['asset']['asset_id']);
                    $effective['asset']['local_preview']=$fresh['local_preview'];
                    if (! hash_equals($fresh['sha256'], $effective['asset']['sha256'])) {
                        $effective['readiness'] = 'invalid';
                    }
                } catch (\Throwable) {
                    $effective['readiness'] = 'invalid';
                }
            }foreach ($effective['frames'] as $role => &$frame) {
                if (! empty($frame['asset'])) {
                    try {
                        $fresh = app(MarketingStoryboardUpload::class)->reference($project, $frame['asset']['asset_id']);
                        $frame['asset']['local_preview']=$fresh['local_preview'];
                        abort_unless(hash_equals($fresh['sha256'], $frame['asset']['sha256']), 409);
                    } catch (\Throwable) {
                        $frame['readiness'] = 'invalid';
                    }
                }
            }unset($frame);
            if(($effective['presenter_required']??false)&&!empty($w->profile['presenter']['avatar_binding'])) {
                try{app(MarketingAvatarBinding::class)->assertUsable($project,$owner,$w->profile['presenter']['avatar_binding']);}
                catch(\Throwable){foreach($effective['frames'] as $role=>&$f){if(app(MarketingStoryboardImages::class)->hasPerson($effective,$role)&&$f['required'])$f['readiness']='invalid';}unset($f);$effective['readiness']='invalid';}
            }
            if(isset($effective['scene_design'])){try{app(MarketingStoryboardImages::class)->references($project,$effective,$w->profile);}catch(\Throwable){foreach($effective['frames'] as &$f)if($f['required'])$f['readiness']='invalid';unset($f);$effective['readiness']='invalid';}}
            foreach($effective['frames'] as $role=>$frame){
                if(!empty($frame['candidate_asset']) || !empty($frame['employee_acceptance']['sync_framing']) || (($frame['readiness']??'')==='pending_sync'&&!empty($frame['asset']))){
                    $decisions=app(MarketingStoryboardDecisions::class);
                    $frame['existing_asset_review_required']=empty($frame['candidate_asset'])&&($frame['readiness']??'')==='pending_sync'&&!empty($frame['asset']);
                    $frame['adoption_issue']=$decisions->adoptionIssue($project,$effective,$role,true);
                    $frame['adoption_technical_issue']=$decisions->adoptionIssue($project,$effective,$role,true,true);
                    $frame['qa_override_required']=!empty($frame['adoption_issue'])&&empty($frame['adoption_technical_issue']);
                    $frame['continuity_ack_required']=$decisions->unassessedContinuity($frame);
                    $synced=$decisions->framingSync($effective,$role);
                    $frame['accepted_framing_needs_sync']=($frame['readiness']??'')==='employee_declared_local_validated'&&empty($frame['candidate_asset'])&&[$effective['visual'],$effective['scene_design']['person_placement']??'',$frame['instruction']]!==[$synced['visual'],$synced['scene_design']['person_placement']??'',$synced['frames'][$role]['instruction']];
                    $frame['framing_sync_preview']=['before'=>['visual'=>$effective['visual'],'director'=>$effective['director'],'placement'=>$effective['scene_design']['person_placement']??'','instruction'=>$frame['instruction']], 'after'=>['visual'=>$synced['visual'],'director'=>$synced['director'],'placement'=>$synced['scene_design']['person_placement']??'','instruction'=>$synced['frames'][$role]['instruction']]];
                    $effective['frames'][$role]=$frame;
                }
            }unset($frame);
            $scenes[] = array_merge($effective, ['scene_id' => $id, 'revision_id' => $r->id, 'ordinal' => $i + 1, 'image_signature' => $r->signature]);
        }

        $sourceFresh = $this->sourceFresh($w, $p);
        $blockers = [];
        if (! $sourceFresh) {
            $blockers[] = ['code' => 'source_changed', 'message' => '项目来源已变化，请先核对当前故事。'];
        }
        foreach ($scenes as $scene) {
            if(!empty($scene['director_needs_detail']))$blockers[]=['code'=>'director_detail_required','scene_id'=>$scene['scene_id'],'message'=>'第'.$scene['ordinal'].'段导演稿文字已保存，详细导演设计待补齐。'];
            $visualReady = in_array($scene['readiness'], ['qa_passed', 'employee_declared_local_validated'], true);
            if (! $visualReady) {
                $blockers[] = ['code' => 'frame_pending', 'scene_id' => $scene['scene_id'], 'message' => '第'.$scene['ordinal'].'段画面待准备。'];
                if (($scene['presenter_required'] ?? false) && ($w->profile['presenter']['mode'] ?? 'none') !== 'preset') {
                    $blockers[] = ['code' => 'presenter_required', 'scene_id' => $scene['scene_id'], 'message' => '第'.$scene['ordinal'].'段需要人物，请上传或选择有效人物参考。'];
                }
            }
            foreach ($scene['frames'] as $role => $frame) {
                if ($frame['required'] && (empty($frame['asset']) || ! in_array($frame['readiness'], ['qa_passed', 'employee_declared_local_validated'], true))) {
                    $label=['start_frame'=>'首帧','end_frame'=>'尾帧','extra_image'=>'补充帧'][$role]??$role;
                    $state=match($frame['readiness']??''){'pending_sync'=>'旧图待同步新版导演稿','invalid'=>'参考或图片已失效，需重新核对',default=>!empty($frame['candidate_asset'])||!empty($frame['asset'])?'已有候选，待核对或调整':'尚未生成'};
                    $blockers[] = ['code' => 'frame_required', 'scene_id' => $scene['scene_id'], 'role' => $role, 'message' => '第'.$scene['ordinal'].'段'.$label.'：'.$state.'。'];
                }
            }
        }
        $auth = MarketingPreparationAuthorization::where('project_id', $project)->where('story_version', $w->version)->pluck('id');
        $tasks = MarketingStoryboardTask::whereIn('revision_id', array_column($scenes, 'revision_id'))->whereIn('authorization_id', $auth)->get(['id', 'revision_id', 'status']);
        $visualRevisionIds = array_column(array_filter($scenes, fn ($scene) => ! app(MarketingStoryboardPackage::class)->ready($scene)), 'revision_id');
        $tasks = $tasks->filter(fn ($task) => in_array($task->revision_id, $visualRevisionIds, true));
        $progress = ['active' => $tasks->whereIn('status', ['queued', 'requesting', 'audio_requesting'])->count(), 'unknown' => $tasks->where('status', 'unknown')->count(), 'blocked' => $tasks->where('status', 'blocked')->count(), 'tasks' => $tasks->values()->toArray()];
        if ($progress['unknown']) {
            $blockers[] = ['code' => 'provider_unknown', 'message' => '供应商结果或费用待核对，已暂停自动调用。'];
        }
        if ($progress['blocked']) {
            $blockers[]=['code'=>'frame_quality_blocked','message'=>'图片检查未通过，已保留结果并暂停生成；导演稿与历史图片仍保留。'];
        }

        $profile=$w->profile;$profile['production_tier']??=$p->preferences['production_tier']??'ordinary';
        if(!empty($profile['presenter']['reference']['asset_id'])){
            try{$ref=app(MarketingStoryboardUpload::class)->reference($project,$profile['presenter']['reference']['asset_id']);$profile['presenter']['reference']['local_preview']=$ref['local_preview'];}catch(\Throwable){}
        }
        return ['video_settings'=>app(MarketingVideoSettings::class)->view($p,$w->profile,$scenes), 'contract' => 'storyboard-workspace-v1', 'story_id' => $w->id, 'story_version' => $w->version, 'source_revision_id' => $w->source_revision_id, 'profile' => $profile, 'material_planning'=>app(MarketingStoryboardDecisions::class)->planningInfo($project), 'production_advice'=>array_map(fn($scene)=>['scene_id'=>$scene['scene_id'],'ordinal'=>$scene['ordinal']]+app(MarketingVideoRoutes::class)->preview($scene,$profile['production_tier']),$scenes), 'blockers' => $blockers, 'preparation_progress' => $progress, 'source_fresh' => $this->sourceFresh($w, $p), 'scenes' => $scenes, 'timing' => app(MarketingStoryboardTiming::class)->calculate(array_map(fn($scene)=>app(MarketingVideoSettings::class)->apply($scene,$p,$w->profile,$scene['scene_id']),$scenes), $w->profile), 'readiness' => $this->sourceFresh($w, $p) && collect($scenes)->every(fn ($scene) => app(MarketingStoryboardPackage::class)->ready($scene)), 'last_confirmation' => $w->last_confirmation];
    }

    public function sourceFingerprint(MarketingProject $p): string
    {
        return hash('sha256', json_encode([$p->current_revision_id, $p->currentRevision?->document, $p->snapshot, $p->brief, ($p->preferences===null?null:\Illuminate\Support\Arr::except($p->preferences,['production_tier'])), $p->selected_concept], JSON_THROW_ON_ERROR));
    }

    public function sourceFresh(Workspace $w, MarketingProject $p): bool
    {
        return $w->source_revision_id === $p->current_revision_id && hash_equals($w->profile['source_fingerprint'] ?? '', $this->sourceFingerprint($p));
    }

    public function effectiveContent(MarketingSceneRevision $r): array
    {
        $effective = app(MarketingStoryboardPackage::class)->content($r->content);
        unset($effective['voice_asset']);
        foreach (MarketingStoryboardTask::where('revision_id', $r->id)->where('status','!=','historical')->orderBy('created_at')->get() as $task) {
            foreach ($task->result['frames'] ?? [] as $role => $frame) {
                if(isset($effective['frames'][$role])&&!empty($frame['asset'])&&!in_array($frame['readiness']??'',['qa_passed','employee_declared_local_validated'],true)){
                    try{
                        $candidate=app(MarketingStoryboardUpload::class)->reference($task->project_id,$frame['asset']['asset_id']);
                        if(hash_equals($frame['asset']['sha256'],$candidate['sha256'])){
                            $effective['frames'][$role]['candidate_asset']=$candidate;
                            $effective['frames'][$role]['qa']=$frame['qa']??null;
                        }
                    }catch(\Throwable){}
                }
                if (isset($effective['frames'][$role]) && in_array($frame['readiness'] ?? '', ['qa_passed', 'employee_declared_local_validated'], true)) {
                    $effective['frames'][$role] = array_replace($effective['frames'][$role], $frame);
                }
            }
            if (! empty($task->result['asset']) && (($task->result['qa']['status'] ?? '') === 'pass' || ($task->result['readiness'] ?? '') === 'employee_declared_local_validated')) {
                $effective['frames']['start_frame'] = array_replace($effective['frames']['start_frame'], ['asset' => $task->result['asset'], 'readiness' => $task->result['readiness'] ?? 'qa_passed', 'qa' => $task->result['qa'] ?? null]);
            }
        }
        // An unchanged manual save can retain a blocked candidate from the same immutable design.
        // This is preview reuse only: it never promotes an image or replays a supplier request.
        $parent=isset($effective['scene_design'])?$r->parent_id:null;
        for($depth=0;$parent&&$depth<12;$depth++){
            if(collect($effective['frames'])->every(fn($f)=>!$f['required']||!empty($f['asset'])||!empty($f['candidate_asset'])))break;
            $prior=MarketingSceneRevision::find($parent);
            if(!$prior||$prior->scene_id!==$r->scene_id)break;
            if(hash_equals($r->signature,$prior->signature)){
                foreach(MarketingStoryboardTask::where('revision_id',$prior->id)->where('status','blocked')->orderByDesc('created_at')->get() as $task){
                    foreach($task->result['frames']??[] as $role=>$f){
                        if(!isset($effective['frames'][$role])||!empty($effective['frames'][$role]['asset'])||!empty($effective['frames'][$role]['candidate_asset'])||empty($f['asset']))continue;
                        try{$ref=app(MarketingStoryboardUpload::class)->reference($task->project_id,$f['asset']['asset_id']);if(hash_equals($f['asset']['sha256'],$ref['sha256'])){$effective['frames'][$role]['candidate_asset']=$ref;$effective['frames'][$role]['candidate_revision_id']=$prior->id;$effective['frames'][$role]['qa']=$f['qa']??null;}}catch(\Throwable){}
                    }
                }
            }
            $parent=$prior->parent_id;
        }
        $start = $effective['frames']['start_frame'];
        if (empty($start['asset']) && ! empty($r->content['asset'])) {
            $effective['frames']['start_frame'] = array_replace($start, ['asset' => $r->content['asset'], 'readiness' => $r->content['readiness'], 'qa' => $r->content['qa'] ?? null]);
        }
        $effective['asset'] = $effective['frames']['start_frame']['asset'];
        $effective['readiness'] = $effective['frames']['start_frame']['readiness'];
        $effective['qa'] = $effective['frames']['start_frame']['qa'];

        return $effective;
    }

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    public function transact(string $project, int $owner, string $request, array $payload, callable $callback): array
    {
        abort_unless(Str::isUuid($request), 422, '请求编号无效。');

        return DB::transaction(function () use ($project, $owner, $request, $payload, $callback) {
            $p = $this->project($project, $owner, true);
            $digest = $this->digest($payload);
            $done = MarketingStoryboardCommand::where('project_id', $project)->where('request_id', $request)->first();
            if ($done) {
                abort_unless(hash_equals($done->digest, $digest), 409, '相同请求编号不能用于其他内容。');

                return $done->result;
              }
              $this->assertEditable($project, $owner);
              $callback($p);
            $result = $this->snapshot($project, $owner);
            MarketingStoryboardCommand::create(['project_id' => $project, 'request_id' => $request, 'digest' => $digest, 'result' => $result]);

            return $result;
        });
    }

    public function assertEditable(string $project, int $owner): void
    {
        $snapshot = $this->snapshot($project, $owner);
        $progress = $snapshot['preparation_progress'] ?? [];
        abort_if(($progress['active'] ?? 0) > 0 || ($progress['unknown'] ?? 0) > 0, 409, '画面生成中或结果待核对，可继续查看；完成后可保存修改。');
    }

    public function initialize(string $project, int $owner, string $request, int $expectedProjectVersion): array
    {
        return $this->transact($project, $owner, $request, ['initialize', $expectedProjectVersion], function ($p) use ($expectedProjectVersion) {
            abort_unless($p->version === $expectedProjectVersion, 409, '项目已改变，请刷新。');
            abort_if(Workspace::where('project_id', $p->id)->exists(), 409, '工作区已初始化。');
            $source = $p->currentRevision;
            abort_unless($source, 422, '请先准备故事分镜。');
            $order = [];
            $profile = ['source_fingerprint' => $this->sourceFingerprint($p), 'legacy_settings' => ['preferences' => $p->preferences, 'profile' => $source->document['profile'] ?? null], 'production_tier'=>$p->preferences['production_tier']??'ordinary', 'requested_seconds' => (int) ($p->brief['duration_seconds'] ?? 15), 'presenter' => ['mode' => 'none'], 'voice' => ['mode' => 'auto']];
            $profile['presenter']['status'] = ($p->preferences['video_type'] ?? '') === 'presenter' ? 'needs_configuration' : 'explicit_none';
            $profile['voice']['status'] = 'arrange_at_step4';
            foreach ($source->document['scenes'] ?? [] as $index => $s) {
                $sourceShot = $source->document['director_plan']['shots'][$index] ?? null;
                $sceneShot = $sourceShot;
                if ($sceneShot) {
                    $offset = (int) ($sourceShot['start'] ?? 0);
                    $sceneShot['start'] = 0;
                    $sceneShot['end'] = (int) $s['duration'];
                    foreach ($sceneShot['beats'] ?? [] as $beatIndex => $beat) {
                        $sceneShot['beats'][$beatIndex]['start'] = $beat['start'] - $offset;
                        $sceneShot['beats'][$beatIndex]['end'] = $beat['end'] - $offset;
                    }
                }
                $content = app(MarketingStoryboardContract::class)->content(['seconds' => (int) ($s['duration'] ?? 5), 'message' => collect([$s['title'] ?? '', $s['purpose'] ?? '', $s['subtitle'] ?? ''])->first(fn ($value) => is_string($value) && trim($value) !== '') ?? '分镜信息', 'visual' => $s['visual'], 'voiceover' => $s['voiceover'] ?? '', 'subtitle' => $s['subtitle'] ?? '', ...($sceneShot ? ['director' => $sceneShot] : []), ...(isset($s['material_plan']) ? ['material_plan'=>$s['material_plan']] : []), ...(! empty($s['frames']) ? ['frames' => $s['frames']] : []), ...(isset($s['rhythm'])?['rhythm'=>$s['rhythm']]:[]), 'presenter_required' => (bool) ($s['presenter_required'] ?? (($p->preferences['video_type'] ?? '') === 'presenter'))]);
                foreach ($source->document['material_package']['outputs'] ?? [] as $output) {
                    if (($output['scene_number'] ?? 0) !== $index + 1 || ! in_array($output['role'] ?? '', MarketingStoryboardPackage::ROLES, true)) {
                        continue;
                    }$role = $output['role'];
                    $content['frames'][$role]['required'] = true;
                    $content['frames'][$role]['instruction'] = $output['preparation'] ?? $content['frames'][$role]['instruction'];
                    if (($output['status'] ?? '') === 'approved' && ! empty($output['asset_id'])) {
                        try {
                            $ref = app(MarketingStoryboardUpload::class)->reference($p->id, $output['asset_id']);
                            abort_unless(hash_equals($output['sha256'] ?? '', $ref['sha256']), 409);
                            $content['_frame_assets'][$role] = $ref;
                        } catch (\Throwable) {
                        }
                    }
                }
                $content['source_context'] = ['revision_id' => $source->id, 'scene_number' => $index + 1, 'scene' => $s, 'director' => $sourceShot];
                $legacy = collect($source->document['material_package']['outputs'] ?? [])->first(fn ($o) => ($o['scene_number'] ?? 0) === $index + 1 && ($o['role'] ?? '') === 'start_frame' && ! empty($o['asset_id']));
                if ($legacy) {
                    try {
                        $content['_previous_asset'] = app(MarketingStoryboardUpload::class)->reference($p->id, $legacy['asset_id']);
                        $content['legacy_provenance'] = ['revision_id' => $source->id, 'scene_number' => $index + 1, 'output_id' => $legacy['id'] ?? null, 'status' => $legacy['status'] ?? 'unknown'];
                    } catch (\Throwable) {/* Missing history asset remains pending, never replaced by another scene. */
                    }
                }
                $order[] = $this->createScene($p->id, $content, $profile)->id;
            }abort_if(count($order) < 1 || count($order) > 12, 422, '分镜数量必须为1至12段。');
            Workspace::create(['project_id' => $p->id, 'source_revision_id' => $source->id, 'scene_order' => $order, 'profile' => $profile, 'version' => 1]);
        });
    }

    private function createScene(string $project, array $content, array $profile, ?string $from = null): MarketingScene
    {
        $id = (string) Str::uuid();
        $rid = (string) Str::uuid();
        $row = MarketingScene::create(['id' => $id, 'project_id' => $project, 'current_revision_id' => $rid, 'replaced_from_id' => $from]);
        $this->revision($row, $content, $profile, $rid);

        return $row;
    }

    private function revision(MarketingScene $row, array $content, array $profile, ?string $rid = null): void
    {
        $rid ??= (string) Str::uuid();
        $saveScope=$content['_save_scope']??null;
        unset($content['_save_scope']);
        $old = MarketingSceneRevision::find($row->current_revision_id);
        $basis = $old ? $this->effectiveContent($old) : [];
        if ($old && ($content['director']['origin'] ?? '') === 'compiled_scene_defaults' && $content['visual'] === $basis['visual'] && $content['seconds'] === $basis['seconds']) {
            $content['director'] = $basis['director'];
        }
        $sig = app(MarketingStoryboardContract::class)->signature($content, $profile);
        $same = $old && hash_equals($old->signature, $sig);
        $manual = $content['_asset'] ?? null;
        $previous = $content['_previous_asset'] ?? null;
        unset($content['_previous_asset']);
        unset($content['_asset']);
        $content['asset'] = $manual ?? ($same ? ($basis['asset'] ?? null) : null);
        $content['previous_asset'] = $previous ?? ($same ? null : ($basis['asset'] ?? $basis['previous_asset'] ?? null));
        $content['readiness'] = $manual ? 'employee_declared_local_validated' : ($same ? ($basis['readiness'] ?? 'pending') : 'pending');
        $bound = $content['_frame_assets'] ?? [];
        unset($content['_frame_assets']);
        foreach ($content['frames'] as $role => &$frame) {
            $frame['asset'] = $bound[$role] ?? ($role === 'start_frame' ? $content['asset'] : ($same ? ($basis['frames'][$role]['asset'] ?? null) : null));
            $frame['readiness'] = isset($bound[$role]) ? 'employee_declared_local_validated' : ($role === 'start_frame' ? $content['readiness'] : ($same ? ($basis['frames'][$role]['readiness'] ?? ($frame['required'] ? 'pending' : 'not_required')) : ($frame['required'] ? 'pending' : 'not_required')));
            $frame['qa'] = $same ? ($basis['frames'][$role]['qa'] ?? null) : null;
            if($same&&!empty($basis['frames'][$role]['employee_acceptance'])&&($frame['asset']['sha256']??null)===($basis['frames'][$role]['asset']['sha256']??null))$frame['employee_acceptance']=$basis['frames'][$role]['employee_acceptance'];
        }unset($frame);
        $startChanged = $old && ($content['frames']['start_frame']['asset']['sha256'] ?? null) !== ($basis['frames']['start_frame']['asset']['sha256'] ?? null);
        if ($startChanged) {
            foreach (['end_frame', 'extra_image'] as $role) {
                if (! isset($bound[$role]) && ! empty($content['frames'][$role]['asset'])) {
                    $content['frames'][$role]['readiness'] = 'pending_qa';
                    $content['frames'][$role]['qa'] = null;
                }
            }
        }
        if($saveScope && $old){
            $content['change_impact']=app(MarketingSceneExecutionPolicy::class)->impact($basis,$content,$profile);
            $roles=$saveScope['frames']??array_keys(array_filter($content['frames'],fn($f)=>$f['required']));
            foreach($content['frames'] as $role=>&$frame){
                $selected=$saveScope['images']&&in_array($role,$roles,true);
                if($selected){
                    $frame['asset']=null;$frame['readiness']=$frame['required']?'pending':'not_required';$frame['qa']=null;
                }else{
                    $frameSame=(!$saveScope['director']||!in_array($role,$content['change_impact']['images'],true))&&!($saveScope['images']&&((in_array('start_frame',$roles,true)&&$role!=='start_frame')||(in_array('end_frame',$roles,true)&&$role==='extra_image')));
                    $frame['asset']=$basis['frames'][$role]['asset']??$basis['frames'][$role]['candidate_asset']??null;
                    $frame['readiness']=$frameSame?($basis['frames'][$role]['readiness']??'pending'):($frame['required']?'pending_sync':'not_required');
                    $frame['qa']=$frameSame?($basis['frames'][$role]['qa']??null):null;
                    if($frameSame&&!empty($basis['frames'][$role]['employee_acceptance']))$frame['employee_acceptance']=$basis['frames'][$role]['employee_acceptance'];
                }
            }unset($frame);
            $content['asset']=$content['frames']['start_frame']['asset'];
            $content['readiness']=$content['frames']['start_frame']['readiness'];
            $content['generation_roles']=$saveScope['images']?$roles:[];
        }
        $content['source_context'] = $basis['source_context'] ?? ($content['source_context'] ?? null);
        MarketingSceneRevision::create(['id' => $rid, 'scene_id' => $row->id, 'parent_id' => $old?->id, 'content' => $content, 'signature' => $sig]);
        $row->update(['current_revision_id' => $rid]);
    }

    public function apply(string $project, int $owner, string $request, int $expectedStoryVersion, array $change): array
    {
        return $this->transact($project, $owner, $request, [$expectedStoryVersion, $change], function () use ($project, $expectedStoryVersion, $change) {
            $w = Workspace::where('project_id', $project)->lockForUpdate()->firstOrFail();
            abort_unless($this->sourceFresh($w, MarketingProject::findOrFail($project)), 409, '项目资料已改变，请重新核对故事来源。');
            abort_unless($w->version === $expectedStoryVersion, 409, '故事已更新，请刷新。');
            $order = $w->scene_order;
            $type = $change['type'] ?? '';
            $id = $change['scene_id'] ?? null;
            $at = array_search($id, $order, true);
            $contract = app(MarketingStoryboardContract::class);
            $prepareContent = function (array $c) use ($contract, $change, $project) {
                $out = $contract->content($c);
                  if(array_key_exists('refine_manual',$change)&&!$change['refine_manual']&&($change['mode']??'')==='manual'&&($out['director']['origin']??'')==='compiled_scene_defaults')$out['director_needs_detail']=true;
                  if(isset($change['update_scope'])){
                      $out['_save_scope']=$change['update_scope'];
                      if(($change['mode']??'')==='image_only')$out['image_adjustment']=['instruction'=>$change['intent'],'roles'=>$change['update_scope']['frames']??MarketingStoryboardPackage::ROLES];
                  }
                foreach ($change['frame_assets'] ?? [] as $role => $asset) {
                    abort_unless(in_array($role, MarketingStoryboardPackage::ROLES, true), 422);
                    $ref = app(MarketingStoryboardUpload::class)->reference($project, $asset);
                    abort_unless($ref['kind'] === 'image', 422, '关键帧需要图片。');
                    $out['_frame_assets'][$role] = $ref;
                }
                if (! empty($change['asset_id'])) {
                    $out['_asset'] = app(MarketingStoryboardUpload::class)->reference($project, $change['asset_id']);
                }

                return $out;
            };
            if ($type === 'insert') {
                if (isset($change['before_id'])) {
                    $at = array_search($change['before_id'], $order, true);
                    abort_if($at === false, 422, '插入位置已改变。');
                } elseif (isset($change['after_id'])) {
                    $at = array_search($change['after_id'], $order, true);
                    abort_if($at === false, 422, '插入位置已改变。');
                    $at++;
                } else {
                    $at = count($order);
                }$row = $this->createScene($project, $prepareContent($change['content']), $w->profile);
                array_splice($order, $at, 0, [$row->id]);
            } elseif ($type === 'restore') {
                $row = MarketingScene::where('project_id', $project)->whereKey($id)->firstOrFail();
                abort_unless($row->removed_at && $at === false, 422, '分镜不能恢复。');
                $row->update(['removed_at' => null]);
                array_splice($order, min(max((int) ($change['index'] ?? count($order)), 0), count($order)), 0, [$id]);
            } else {
                abort_if($at === false, 422, '当前分镜不存在。');
                $row = MarketingScene::where('project_id', $project)->findOrFail($id);
                if ($type === 'edit') {
                    $prior = $this->effectiveContent(MarketingSceneRevision::findOrFail($row->current_revision_id));
                    $next = $change['content'];
                    if (! isset($next['frames'])) {
                        $sameVisual = ($next['visual'] ?? null) === $prior['visual'] && ($next['seconds'] ?? null) === $prior['seconds'];
                        $next['frames'] = array_map(fn ($frame) => array_intersect_key($frame, array_flip($sameVisual ? ['required', 'instruction','state','person_presence'] : ['required','person_presence'])), $prior['frames']);
                        // Inherited frames execute the saved director; explicit frame payloads stay strict.
                        if($sameVisual){
                            foreach(['start_frame'=>'start_state','end_frame'=>'end_state'] as $role=>$stateKey){
                                $state=($next['director']??$prior['director'])[$stateKey]??null;
                                $oldState=$next['frames'][$role]['state']??null;
                                if(is_string($state)&&is_string($oldState)&&$state!==$oldState){
                                    $next['frames'][$role]['state']=$state;
                                    $next['frames'][$role]['instruction']=str_replace($oldState,$state,$next['frames'][$role]['instruction']??'');
                                }
                            }
                        }
                    }
                    if(($next['visual']??null)===$prior['visual']&&($next['seconds']??null)===$prior['seconds']){
                        foreach(['scene_design','director','rhythm','material_plan'] as $field)if(!array_key_exists($field,$next)&&array_key_exists($field,$prior))$next[$field]=$prior[$field];
                    }
                    foreach (['fact_refs', 'presenter_required'] as $field) {
                        if (! array_key_exists($field, $next) && array_key_exists($field, $prior)) {
                            $next[$field] = $prior[$field];
                        }
                    }$this->revision($row, $prepareContent($next), $w->profile);
                } elseif ($type === 'replace') {
                    $new = [];
                    foreach ($change['contents'] ?? [] as $c) {
                        $new[] = $this->createScene($project, $prepareContent($c), $w->profile, $id)->id;
                    }abort_if(! $new, 422, '替换分镜不能为空。');
                    $row->update(['removed_at' => now()]);
                    array_splice($order, $at, 1, $new);
                } elseif ($type === 'remove') {
                    $row->update(['removed_at' => now()]);
                    array_splice($order, $at, 1);
                } elseif ($type === 'move') {
                    $to = (int) ($change['index'] ?? -1);
                    abort_if($to < 0 || $to >= count($order), 422, '排序位置无效。');
                    array_splice($order, $at, 1);
                    array_splice($order, $to, 0, [$id]);
                } else {
                    abort(422, '不支持的修改。');
                }
            }
            abort_if(count($order) < 1 || count($order) > 12, 422, '分镜数量必须为1至12段。');
            $w->update(['scene_order' => $order, 'version' => $w->version + 1, 'last_confirmation' => null]);
        });
    }
}
