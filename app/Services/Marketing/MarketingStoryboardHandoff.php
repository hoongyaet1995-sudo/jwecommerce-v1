<?php

namespace App\Services\Marketing;

use App\Models\MarketingStoryboardWorkspace as Workspace;

class MarketingStoryboardHandoff
{
    public function confirm(string $project, int $owner, string $request, int $storyVersion): array
    {
        $service = app(MarketingStoryboardWorkspace::class);

        return $service->transact($project, $owner, $request, ['confirm', $storyVersion], function () use ($project, $owner, $storyVersion, $service, $request) {
            $s = $service->snapshot($project, $owner);
            abort_unless($s['source_fresh'], 409, '项目资料已改变，不能交接旧故事。');
            abort_unless($s['story_version'] === $storyVersion, 409, '故事已改变。');
            abort_unless($s['readiness'] && ! $s['timing']['requires_attention'], 422, '请先准备当前版本画面并解决时长问题。');
            $production = $this->build($project, $owner, $s['story_id']);
            Workspace::where('project_id', $project)->update(['last_confirmation' => json_encode(['request_id' => $request, 'confirmed_by' => $owner, 'confirmed_at' => now()->toIso8601String(), 'snapshot' => $production], JSON_THROW_ON_ERROR)]);
        });
    }

    public function build(string $project, int $owner, string $storyId): array
    {
        $s = app(MarketingStoryboardWorkspace::class)->snapshot($project, $owner);
        abort_unless($s['story_id'] === $storyId && $s['readiness'], 422, '素材尚未准备。');
        abort_unless($s['source_fresh'], 409, '项目资料已改变。');
        $route = ['route' => 'deferred_to_step4', 'voice_snapshot' => $s['profile']['voice'] ?? ['mode' => 'none'], 'formal_audio_generated' => false];
        $p = app(MarketingStoryboardWorkspace::class)->project($project, $owner);
        $source = $p->currentRevision;
        $s['scenes']=app(MarketingVideoSettings::class)->productionScenes($s);
        $transitions = [];
        foreach ($s['scenes'] as $i => $scene) {
            if (! isset($s['scenes'][$i + 1])) {
                continue;
            }$next = $s['scenes'][$i + 1];
            $transitions[] = ['from' => $scene['scene_id'], 'to' => $next['scene_id'], 'method' => 'cut', 'outgoing' => $scene['director']['end_state'], 'incoming' => $next['director']['start_state'], 'continuity' => $next['director']['continuity']];
        }

        return ['story_id'=>$s['story_id'], 'source_revision_id' => $s['source_revision_id'], 'source_context' => ['project_id' => $project, 'language' => $p->preferences['language'] ?? $p->brief['language'] ?? 'English', 'brief' => $p->brief, 'snapshot' => $p->snapshot, 'historical_design_only' => true, 'original_director_plan' => $source->document['director_plan'] ?? null, 'original_transition_plan' => $source->document['transition_plan'] ?? null, 'original_material_requirements' => $source->document['material_package']['requirements'] ?? []], 'transitions' => $transitions, 'stage' => 'step3-prepared', 'director_plan' => ['shots' => array_map(fn ($scene) => ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'design' => $scene['director']], $s['scenes'])], 'timeline' => $s['timing']['segments'], 'frame_inventory' => array_map(fn ($scene) => ['scene_id' => $scene['scene_id'], 'revision_id' => $scene['revision_id'], 'roles' => $scene['frames']], $s['scenes']), 'contract' => 'storyboard-production-v1', 'story_version' => $s['story_version'], 'scene_map' => $s['scenes'], 'profile' => $s['profile'], 'voice_route' => $route, 'timing' => $s['timing'], 'readiness_digest' => hash('sha256', json_encode($s['scenes'], JSON_THROW_ON_ERROR))];
    }
}
