<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunMarketplaceCron extends Command
{
    protected $signature = 'marketplace:cron';
    protected $description = 'Process at most one queued Marketplace task without running unrelated schedules';

    public function handle(): int
    {
        if (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true) {
            $this->components->info('Marketplace operations are disabled; no queued work consumed.');
            return self::SUCCESS;
        }

        // Longer than the current catalog job's 1200-second timeout.
        $lock = Cache::lock('marketplace:cron', 1500);
        if (!$lock->get()) {
            $this->components->info('Marketplace cron is already running; this invocation will exit.');
            return self::SUCCESS;
        }
        try {
            return $this->call('queue:work', [
                'connection'=>'marketplace', '--queue'=>'marketplace',
                '--stop-when-empty'=>true, '--max-jobs'=>1,
                '--tries'=>1, '--timeout'=>1200, '--no-interaction'=>true,
            ]);
        } finally {
            $lock->release();
        }
    }
}
