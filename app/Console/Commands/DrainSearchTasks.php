<?php

namespace App\Console\Commands;

use App\Models\SearchTask;
use App\Services\Search\SearchOptimization;
use Illuminate\Console\Command;

class DrainSearchTasks extends Command
{
    protected $signature = 'search:work {--limit=10} {--watch : Keep processing only Search tasks for local development}';

    protected $description = 'Process bounded durable search optimization tasks without exposing provider credentials';

    public function handle(SearchOptimization $service): int
    {
        do {
            $this->drain($service);
            if ($this->option('watch')) {
                sleep(5);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }

    private function drain(SearchOptimization $service): void
    {
        // Resume work deferred by the retired daily allowance policy.
        SearchTask::where('status', 'queued')->where('error', 'daily_task_limit')->update(['available_at' => now(), 'error' => null]);
        SearchTask::where('status', 'running')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'error' => 'worker_interrupted', 'finished_at' => now()]);
        $count = 0;
        foreach (SearchTask::where('status', 'queued')->where('available_at', '<=', now())->oldest('id')->limit(max(1, min(100, (int) $this->option('limit'))))->get() as $task) {
            $done = $service->run($task);
            $this->line(json_encode(['id' => $done->id, 'kind' => $done->kind, 'entity' => $done->entity_id, 'status' => $done->status, 'error' => $done->error], JSON_THROW_ON_ERROR));
            $count++;
        }
        $this->info('Processed '.$count.' tasks.');

    }
}
