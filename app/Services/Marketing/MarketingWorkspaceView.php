<?php

namespace App\Services\Marketing;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionApproval;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\Product;

class MarketingWorkspaceView
{
    private function imageUrl(string $path): ?string
    {
        if (str_contains(rawurldecode($path), '..') || str_contains($path, ':') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return null;
        }
        if (str_starts_with($path, '/images/') || str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }
        if (! str_starts_with($path, '/') && ! str_contains($path, ':') && ! str_contains($path, '..')) {
            return asset('storage/'.$path);
        }

        return null;
    }

    public function data(?MarketingProject $project, string $screen): array
    {
        $screens = ['source', 'preferences', 'guided', 'brief', 'concepts', 'materials', 'confirmation', 'finished'];
        $step = ['guided' => 1, 'brief' => 1, 'concepts' => 2, 'materials' => 3, 'confirmation' => 4, 'finished' => 5][$screen] ?? 0;
        $preferences = $project?->preferences ?? [];
        $planningFields = $project?->planning_state['fields'] ?? [];
        $planningValues = array_map(fn ($field) => $field['value'] ?? null, $planningFields);
        $brief = array_replace($project?->brief ?? [], array_filter($planningValues, fn ($value) => $value !== null));
        if(array_key_exists('duration_seconds',$planningValues)&&$planningValues['duration_seconds']===null)unset($brief['duration_seconds']);
        $production = $project ? MarketingProduction::where('project_id', $project->id)->where('owner_id', $project->owner_id)->where('status', 'completed')->latest()->first() : null;
        $productionPlan = $production ? MarketingProductionPlan::where('project_id', $project->id)->find($production->plan_id) : null;
        $productionRevision = $productionPlan ? MarketingRevision::where('project_id', $project->id)->find($productionPlan->revision_id) : null;
        $currentProduction = $productionPlan && $productionPlan->revision_id === $project->current_revision_id && $productionPlan->project_version === $project->version;
        $revision = $project?->currentRevision;
        $completedThrough = 0;
        if ($project) {
            $legacyReady = ($project->brief['source_confirmed'] ?? false) && ($project->brief['facts_confirmed'] ?? false) && ($project->brief['goal_confirmed'] ?? false) && ($project->brief['audience_confirmed'] ?? false);
            if (($project->planning_state['status'] ?? '') === 'confirmed' || ($project->planning_state === null && $legacyReady)) {
                $completedThrough = 1;
            }
            if ($completedThrough >= 1 && $project->selected_concept !== null) {
                $completedThrough = 2;
            }
            if ($completedThrough >= 2 && $project->current_revision_id && $project->confirmed_revision_id === $project->current_revision_id) {
                $completedThrough = 3;
            }
            if ($completedThrough >= 3 && ($currentProduction || MarketingProductionPlan::where('project_id', $project->id)->where('revision_id', $project->current_revision_id)->where('project_version', $project->version)->whereNotNull('confirmed_at')->exists())) {
                $completedThrough = 4;
            }
            if ($completedThrough >= 4 && $currentProduction && $production->output_sha256 && MarketingProductionApproval::where('production_id', $production->id)->where('output_sha256', $production->output_sha256)->exists()) {
                $completedThrough = 5;
            }
        }
        $progressCurrent = min($completedThrough + 1, 5);

        return compact('project', 'screen', 'screens', 'step', 'brief', 'production', 'revision', 'productionRevision', 'currentProduction', 'preferences', 'planningFields', 'planningValues', 'completedThrough', 'progressCurrent') + [
            'products' => Product::orderBy('title')->limit(100)->get(['id', 'title']),
            'title' => $project?->snapshot['title'] ?? '新视频项目',
            'concepts' => $project?->concepts ?? [],
            'referenceSources' => $project && $screen === 'concepts' ? array_column(app(MarketingCreativePlanning::class)->research($project)['sources'] ?? [], null, 'id') : [],
            'image' => isset($project?->snapshot['images'][0]['path']) ? $this->imageUrl($project->snapshot['images'][0]['path']) : null,
            'summary' => ['产品' => $planningValues['offer'] ?? $project?->snapshot['title'] ?? '待选择', '受众' => $brief['audience'] ?? '待确认', '目的' => MarketingPlanningGuide::GOALS[$brief['goal'] ?? ''] ?? '待确认', '平台与时长' => (implode(' · ', $brief['platforms'] ?? []) ?: '平台待确认').(isset($brief['duration_seconds']) ? ' · '.$brief['duration_seconds'].'秒' : ' · 时长待推荐'), '核心痛点' => $brief['pain_point'] ?? '待AI建议与确认', '差异化卖点' => $brief['differentiator'] ?? '待AI建议与确认', '调性' => $brief['tone'] ?? '待确认'],
        ];
    }
}
