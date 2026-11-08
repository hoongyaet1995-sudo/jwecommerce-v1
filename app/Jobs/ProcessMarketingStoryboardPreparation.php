<?php

namespace App\Jobs;

use App\Models\MarketingStoryboardTask;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMarketingStoryboardPreparation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $runId)
    {
        $this->onConnection('marketing-production');
        $manual=app()->environment('local')&&config('marketing.storyboard.live_grant.mode')==='manual_local'&&MarketingStoryboardTask::find($runId)?->project_id===config('marketing.storyboard.live_grant.project_id');
        $this->onQueue($manual?'marketing-storyboard-manual':'marketing-workspace-video');
    }

    public function handle(): void
    {
        app(MarketingStoryboardPreparation::class)->process($this->runId);
        if (MarketingStoryboardTask::whereKey($this->runId)->where('status', 'queued')->exists()) {
            self::dispatch($this->runId)->delay(now()->addSeconds(2));
        }
    }

    public function failed(?\Throwable $e): void
    {
        $task = MarketingStoryboardTask::find($this->runId);
        if (! $task) {
            return;
        }
        app(MarketingStoryboardProvider::class)->interrupted($task->authorization_id, $task->revision_id);
        if (in_array($task->status, ['requesting', 'audio_requesting'], true)) {
            $task->update(['status' => 'unknown']);
        }
    }
}
