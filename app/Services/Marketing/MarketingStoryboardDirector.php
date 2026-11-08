<?php

namespace App\Services\Marketing;

use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingStoryboardCommand;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/** Runs only within a separately approved, project-scoped provider grant. */
class MarketingStoryboardDirector
{
    public function change(string $project, int $owner, string $request, int $version, array $command): array
    {
        $service = app(MarketingStoryboardWorkspace::class);
        $before = $service->snapshot($project, $owner);
        $scene=collect($before['scenes'])->firstWhere('scene_id',$command['scene_id']??'')??[];
        if(array_key_exists('refine_manual',$command)){
            \Illuminate\Support\Facades\Validator::make($command,['refine_manual'=>'boolean'])->validate();
            if($command['refine_manual']&&($command['mode']??'')==='manual'&&($command['update_scope']['director']??true)){
                app(MarketingStoryboardContract::class)->content($command['content']??[]);
                $command['mode']='ai';
                $command['requested_seconds']=$command['content']['seconds'];
            }
        }
        if(isset($command['update_scope']['director'])&&!$command['update_scope']['director'])$command['mode']='image_only';
        // Duplicate employee submissions return the original saved response without new authorizations or HTTP.
        $old = MarketingStoryboardCommand::where('project_id', $project)->where('request_id', $request)->first();
        if ($old) {
            $prior = MarketingPreparationAuthorization::where('project_id', $project)->where('request_id', $request)->first();

            return app(MarketingStoryboardChanges::class)->change($project, $owner, $request, $version, $prior ? $command + ['authorization_id' => $prior->id] : $command);
        }
        $service->assertEditable($project,$owner);
        $scope=app(MarketingStoryboardSaveScope::class)->validate($command,$scene);
        if($scope && !$scope['director'])$command['mode']='image_only';
        if (($command['mode'] ?? 'manual') !== 'ai') {
            $out=app(MarketingStoryboardChanges::class)->change($project, $owner, $request, $version, $command);
            if(!config('marketing.execution_enabled',false)&&app(MarketingGenerationQuotes::class)->freeManual('changes',['command'=>$command]))return $out+['preparation'=>['tasks'=>[],'blockers'=>[]]];
            return $this->prepare($project, $owner, $out);
        }
        $provider = app(MarketingStoryboardProvider::class);
        foreach (($scope && !$scope['images'])?['semantic']:['semantic', 'image', 'qa'] as $role) {
            abort_unless($provider->allowed($role, $project), 423, '本次 AI 范围与预算尚未批准，未调用真实 API。');
            $provider->role($role);
        }
        $cap = (int) config('marketing.storyboard.live_grant.max_total_microusd', 0);
        $authorization = app(MarketingPreparationBudget::class)->authorize($project, $owner, $request, $version, array_column($before['scenes'], 'revision_id'), $cap);
        // Bind the stable authorization to this employee request for duplicate delivery.
        $out = app(MarketingStoryboardChanges::class)->change($project, $owner, $request, $version, $command + ['authorization_id' => $authorization['id']]);

        return $this->prepare($project, $owner, $out);
    }

    public function initialize(string $project, int $owner, string $request, int $version): array
    {
        $snapshot = app(MarketingStoryboardWorkspace::class)->initialize($project, $owner, $request, $version);
        if(app(MarketingGenerationQuotes::class)->enabled())return ['snapshot'=>$snapshot,'preparation'=>['tasks'=>[],'blockers'=>[]]];

        return $this->prepare($project, $owner, ['snapshot' => $snapshot]);
    }

    public function prepare(string $project, int $owner, array $out): array
    {
        if(app(MarketingGenerationQuotes::class)->enabled()&&!config('marketing.billing.execution_quote_id'))return $out+['preparation'=>['tasks'=>[],'blockers'=>[]]];
        if(isset($out['update_scope'])&&!$out['update_scope']['images'])return $out+['preparation'=>['tasks'=>[],'blockers'=>[]]];
        $current = app(MarketingStoryboardWorkspace::class)->snapshot($project, $owner);
        // A duplicate response may contain an older story; never prepare that old snapshot.
        if (($out['snapshot']['story_version'] ?? 0) !== ($current['story_version'] ?? 0)) {
            return $out + ['preparation' => ['tasks' => [], 'blockers' => [['code' => 'story_changed', 'message' => '故事已变化，请刷新。']]]];
        }
        if(!config('marketing.billing.enabled'))app(MarketingStoryboardManualTesting::class)->activate($project);
        if (app()->environment('local') && config('marketing.storyboard.live_grant.mode') === 'manual_local' && !array_key_exists('affected_scene_ids', $out)) {
            return $out + ['preparation' => ['tasks' => [], 'blockers' => [['code' => 'manual_save_required', 'message' => '设置已保存；请在需要调整的分镜点击“告诉 AI 怎么改”保存后准备图片。']]]];
        }
        $snapshot = $current;
        $affected = $out['affected_scene_ids'] ?? array_column($snapshot['scenes'], 'scene_id');
        $scope = [];
        $blockers = [];
        if (! ($snapshot['source_fresh'] ?? false)) {
            return $out + ['preparation' => ['tasks' => [], 'blockers' => [['code' => 'source_changed', 'message' => '项目来源已变化，请重新核对故事。']]]];
        }
        foreach ($snapshot['scenes'] as $scene) {
            if (! in_array($scene['scene_id'], $affected, true)) {
                continue;
            }
            $imageValid = app(MarketingStoryboardPackage::class)->ready($scene);
            if(!empty($scene['director_needs_detail'])){
                $blockers[]=['code'=>'director_detail_required','scene_id'=>$scene['scene_id'],'message'=>'导演稿文字已保存；请启用 AI 补齐详细导演设计后再准备图片。'];
                continue;
            }

            if (!$imageValid && !app()->environment('testing') && empty($scene['scene_design'])) {
                $blockers[]=['code'=>'scene_design_required','scene_id'=>$scene['scene_id'],'message'=>'第'.$scene['ordinal'].'段需要先通过“告诉 AI 怎么改”补齐场景与关键帧设计。'];
                continue;
            }

            if (! $imageValid && ($scene['presenter_required'] ?? false)) {
                $person = $snapshot['profile']['presenter'] ?? [];
                try {
                    abort_unless(($person['mode'] ?? 'none') === 'preset', 422);
                    app(MarketingPresenterLibrary::class)->resolve($project, $owner, $person['id'], (int) $person['version']);
                } catch (\Throwable) {
                    $blockers[] = ['code' => 'presenter_required', 'scene_id' => $scene['scene_id'], 'message' => '第'.$scene['ordinal'].'段需要人物，请先选择有效授权人物参考。'];

                    continue;
                }
            }
            if (! app(MarketingStoryboardPackage::class)->ready($scene)) {
                $scope[] = $scene['revision_id'];
            }
        }
        $out['snapshot'] = $snapshot;
        $out['preparation'] = ['tasks' => [], 'blockers' => $blockers];
        if (! $scope) {
            return $out;
        }
        $provider = app(MarketingStoryboardProvider::class);
        $provider->allowed('image',$project);
        $grant = config('marketing.storyboard.live_grant', []);
        try {
            abort_unless($provider->allowed('image', $project) && $provider->allowed('qa', $project) && ($grant['owner_id'] ?? 0) === $owner && ((app()->environment('local')&&($grant['mode']??'')==='manual_local')||Carbon::parse($grant['expires_at'] ?? '2000-01-01')->isFuture()), 423);
            $cap = $grant['max_total_microusd'] ?? 0;
            abort_unless(is_int($cap) && $cap > 0 && $cap <= 100000000, 423);
        } catch (\Throwable) {
            $out['preparation']['blockers'][] = ['code' => 'budget_approval_required', 'message' => '首帧准备等待本次范围与预算批准，未调用真实 API。'];

            return $out;
        }
        $request = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'storyboard-prepare:'.hash('sha256', json_encode([$project, $snapshot['story_version'], $scope, $grant['reference'] ?? '', $cap], JSON_THROW_ON_ERROR)));
        $a = app(MarketingPreparationBudget::class)->authorize($project, $owner, $request, $snapshot['story_version'], $scope, $cap);
        $out['preparation'] = app(MarketingStoryboardPreparation::class)->prepare($project,$a['id']) + ['blockers' => $blockers];

        return $out;
    }
}
