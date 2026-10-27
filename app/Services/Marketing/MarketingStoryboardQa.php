<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use App\Models\MarketingStoryboardTask;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\Storage;

class MarketingStoryboardQa
{
    public function review(array $scene, array $asset, array $references, ?string $taskId = null, string $role = 'start_frame'): array
    {
        if (app()->environment('testing') && app()->bound('marketing.storyboard.qa_transport')) {
            $qa = app('marketing.storyboard.qa_transport')($scene, $asset, $references);
            abort_unless(in_array($qa['status'] ?? '', ['pass', 'repair', 'uncertain', 'fail'], true), 422, '检查结果无效。');

            return $qa + ['max_repairs' => 1];
        }
        if (! $taskId) {
            return ['status' => 'uncertain', 'evidence' => '未执行视觉检查', 'max_repairs' => 1];
        }
        $task = MarketingStoryboardTask::findOrFail($taskId);
        $w = Workspace::where('project_id', $task->project_id)->firstOrFail();
        $refs = app(MarketingStoryboardImages::class)->frameReferences($task, $scene, $w->profile, $role);
        $paths = array_map(fn ($r) => $r['path'], $refs);
        $candidate = MarketingAsset::where('project_id', $task->project_id)->where('authorized', true)->findOrFail($asset['asset']['asset_id']);
        $paths['candidate'] = Storage::disk('local')->path($candidate->path);
        $design = app(MarketingStoryboardImages::class)->compile($scene, $w->profile, $refs, $role);
        $cfg = app(MarketingMaterialVisionClient::class)->configuration();
        abort_unless($cfg['model'] === app(MarketingStoryboardProvider::class)->role('qa')['model'], 423, '视觉检查角色与已验证连接不一致。');
        $pricing = app(MarketingStoryboardProvider::class)->role('qa');
        foreach (['input_usd_per_million', 'output_usd_per_million'] as $rate) {
            abort_unless(is_numeric($pricing[$rate] ?? null) && $pricing[$rate] >= 0, 423, '请先核验视觉检查角色单价。');
        }$payload = ['revision_id' => $task->revision_id, 'frame_role' => $role, 'candidate_sha256' => $asset['asset']['sha256'], 'design' => $design, 'connection' => $cfg];
        $result = app(MarketingStoryboardProvider::class)->call('qa', $task->project_id, $task->authorization_id, $payload, function () use ($design, $paths, $pricing) {
            $raw = app(MarketingMaterialVisionClient::class)->inspect($design, $paths);

            return ['status' => match ($raw['verdict']) {
                'pass' => 'pass','revise' => 'repair',default => 'uncertain'
            }, 'evidence' => $raw, 'correction' => $raw['correction'] ?? '', 'actual_microusd' => app(MarketingStoryboardOpenAi::class)->cost($raw['usage'] ?? [], $pricing)];
        });

        return $result + ['max_repairs' => 1];
    }
}
