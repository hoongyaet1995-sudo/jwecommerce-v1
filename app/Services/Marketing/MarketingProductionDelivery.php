<?php

namespace App\Services\Marketing;

use App\Models\{MarketingProduction, MarketingProductionApproval, MarketingProductionPlan, MarketingProject};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MarketingProductionDelivery
{
    public function approve(string $project, string $production, int $actor, string $expectedHash): void
    {
        DB::transaction(function () use ($project, $production, $actor, $expectedHash) {
            $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($project);
            $task = MarketingProduction::where('project_id', $project)->where('owner_id', $actor)->lockForUpdate()->findOrFail($production);
            $plan = MarketingProductionPlan::where('project_id', $project)->findOrFail($task->plan_id);
            $revision=\App\Models\MarketingRevision::where('project_id',$project)->findOrFail($plan->revision_id);
            abort_unless(app(MarketingVideoChange::class)->brandMatches($revision),409,'Restore the original brand before approving this changed video.');
            abort_unless($task->status === 'completed' && hash_equals((string) $task->output_sha256, $expectedHash), 409, 'The completed video changed. Reload and review it again.');
            abort_unless($p->current_revision_id === $plan->revision_id && $p->confirmed_revision_id === $plan->revision_id && $p->version === $plan->project_version && $p->status === 'prepared' && !$p->busy_run_id, 409, 'The project changed. Review the current storyboard and video before approving.');
            $this->completedFile($task);
            $approval = MarketingProductionApproval::where('production_id', $task->id)->first();
            if ($approval) {
                abort_unless(hash_equals($approval->output_sha256, $expectedHash) && $approval->revision_id === $plan->revision_id, 409, 'The saved approval belongs to a different video.');
                return;
            }
            MarketingProductionApproval::create(['production_id' => $task->id, 'revision_id' => $plan->revision_id, 'output_sha256' => $expectedHash, 'approved_by' => $actor, 'approved_at' => now()]);
        });
    }

    public function approvedFile(MarketingProduction $task): string
    {
        $approval = MarketingProductionApproval::where('production_id', $task->id)->first();
        abort_unless($approval, 403, 'Approve this completed video before downloading.');
        $plan = MarketingProductionPlan::where('project_id', $task->project_id)->findOrFail($task->plan_id);
        abort_unless($approval->revision_id === $plan->revision_id && hash_equals($approval->output_sha256, (string) $task->output_sha256), 409, 'The file no longer matches the approved video.');
        return $this->completedFile($task);
    }

    public function completedFile(MarketingProduction $task): string
    {
        $expected = 'marketing-productions/'.$task->id.'/output.mp4';
        abort_unless($task->status === 'completed' && $task->output_path === $expected, 409, 'This production has no completed video.');
        $disk = Storage::disk('local');
        $path = realpath($disk->path($expected));
        abort_unless($path && is_file($path), 404, 'The video file is unavailable.');
        // Reject redirected paths, including symlinks outside the private production directory.
        $root = realpath($disk->path(''));
        abort_unless($root && str_replace('\\', '/', $path) === rtrim(str_replace('\\', '/', $root), '/').'/'.$expected, 409, 'The video file location changed.');
        abort_unless(preg_match('/^[a-f0-9]{64}$/', (string) $task->output_sha256) && hash_equals($task->output_sha256, hash_file('sha256', $path)), 409, 'The video file changed. It must be reviewed again.');
        return $path;
    }
}
