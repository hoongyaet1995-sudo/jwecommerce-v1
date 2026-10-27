<?php

namespace App\Services\Marketing;

use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MarketingCreativeProfiles
{
    public function options(?string $project = null, ?int $owner = null): array
    {
        return ['presenters' => array_map(fn ($item) => Arr::except($item, ['path']), app(MarketingPresenterLibrary::class)->options($project, $owner)), 'voices' => app(MarketingStoryboardVoice::class)->options()];
    }

    public function save(string $project, int $owner, int $storyVersion, array $profile): array
    {
        return DB::transaction(function () use ($project, $owner, $storyVersion, $profile) {
            $service = app(MarketingStoryboardWorkspace::class);
            $service->project($project, $owner, true);
            $service->assertEditable($project, $owner);
            $w = Workspace::where('project_id', $project)->firstOrFail();
            abort_unless($w->version === $storyVersion, 409, '故事已改变。');
            $presenter = $profile['presenter'] ?? [];
            if (($presenter['mode']??'')==='preset' && str_starts_with($presenter['id']??'','avatar:')) {
                $bound=app(MarketingAvatarBinding::class)->bind($project,$owner,substr($presenter['id'],7),(int)($presenter['version']??0));
                $presenter=['mode'=>'preset','id'=>$bound['id'],'version'=>$bound['version']];
            }
            $voice = $profile['voice'] ?? [];
            abort_unless(in_array($presenter['mode'] ?? '', ['none', 'preset'], true) && in_array($voice['mode'] ?? '', ['auto', 'none', 'preset'], true), 422, '选择可用人物与声音；真人模式需先配置授权资产。');
            if ($presenter['mode'] === 'preset') {
                abort_unless(collect($this->options($project, $owner)['presenters'])->contains(fn ($p) => $p['id'] === ($presenter['id'] ?? null) && $p['version'] === ($presenter['version'] ?? null)), 422, '人物参考未配置或失效。');
            }if ($voice['mode'] === 'preset') {
                abort_unless(collect($this->options($project, $owner)['voices'])->contains(fn ($p) => $p['id'] === ($voice['id'] ?? null) && $p['version'] === ($voice['version'] ?? null)), 422, '声线未配置。');
            }if ($presenter['mode'] === 'preset') {
                $selected = app(MarketingPresenterLibrary::class)->resolve($project, $owner, $presenter['id'], (int) $presenter['version']);
                $profile['presenter'] = ['mode' => 'preset', 'id' => $selected['id'], 'version' => $selected['version'], 'name' => $selected['name'], 'kind' => $selected['kind'] ?? 'synthetic', 'reference' => $selected['reference'] ?? null, ...(!empty($selected['avatar_binding'])?['avatar_binding'=>$selected['avatar_binding']]:[])];
            }$profile['voice'] = $voice['mode'] === 'preset' ? array_merge(['mode' => 'preset'], app(MarketingStoryboardVoice::class)->resolve($voice)) : ['mode' => $voice['mode']];
            $profile = array_intersect_key($profile, array_flip(['presenter', 'voice']));
            $merged = array_replace($w->profile, $profile);
            if (Arr::except($w->profile['presenter']??[],['status','reference.local_preview'])===Arr::except($merged['presenter']??[],['reference.local_preview']) && Arr::except($w->profile['voice']??[],['status'])===($merged['voice']??[])) return $service->snapshot($project,$owner);
            if (Arr::except($w->profile['presenter']??[],['name','status','reference.local_preview']) !== Arr::except($merged['presenter']??[],['name','status','reference.local_preview'])) {
                foreach (MarketingScene::whereIn('id', $w->scene_order)->get() as $row) {
                    $old = MarketingSceneRevision::findOrFail($row->current_revision_id);
                    if (! ($old->content['presenter_required'] ?? false)) {
                        continue;
                    }$c = $service->effectiveContent($old);
                    unset($c['voice_asset']);
                    $c['previous_asset'] = $c['asset'] ?? $c['previous_asset'] ?? null;
                    $startAffected=app(MarketingStoryboardImages::class)->hasPerson($c,'start_frame');
                    foreach ($c['frames'] as $role => &$frame) {
                        if(!app(MarketingStoryboardImages::class)->hasPerson($c,$role)){
                            if($startAffected&&$role!=='start_frame'&&$frame['asset']){$frame['readiness']='pending_qa';$frame['qa']=null;}
                            continue;
                        }
                        $frame['previous_asset'] = $frame['asset'] ?? null;
                        $frame['asset'] = null;
                        $frame['qa'] = null;
                        $frame['readiness'] = $frame['required'] ? 'pending' : 'not_required';
                    }unset($frame);
                    $c['asset'] = $c['frames']['start_frame']['asset'];
                    $c['readiness'] = $c['frames']['start_frame']['readiness'];
                    $new = MarketingSceneRevision::create(['scene_id' => $row->id, 'parent_id' => $old->id, 'content' => $c, 'signature' => app(MarketingStoryboardContract::class)->signature($c, $merged)]);
                    $row->update(['current_revision_id' => $new->id]);
                }
            }$w->update(['profile' => $merged, 'version' => $w->version + 1, 'last_confirmation' => null]);

            return $service->snapshot($project, $owner);
        });
    }

    public function resolveProduction(array $profile, array $capabilities): array
    {
        $voice = $profile['voice'] ?? ['mode' => 'none'];
        $blockers = [];
        if (($voice['mode'] ?? 'none') !== 'none' && ! ($capabilities['locked_voice'] ?? false)) {
            $blockers[] = '当前制作路线尚不能兑现所选声线';
        }

        return ['route' => ($voice['mode'] ?? 'none') === 'none' ? 'silent' : 'locked_voice', 'voice_snapshot' => $voice, 'blockers' => $blockers];
    }
}
