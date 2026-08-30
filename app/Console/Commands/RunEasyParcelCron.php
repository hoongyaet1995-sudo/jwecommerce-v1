<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunEasyParcelCron extends Command
{
    protected $signature = 'easyparcel:cron';

    protected $description = 'Run due EasyParcel schedules and drain the default database queue safely';

    public function handle(): int
    {
        $lock = Cache::lock('easyparcel:cron', 55);

        if (! $lock->get()) {
            $this->components->info('EasyParcel cron is already running; this invocation will exit.');

            return self::SUCCESS;
        }

        try {
            $scheduleExitCode = $this->call('schedule:run', [
                '--no-interaction' => true,
            ]);

            if ($scheduleExitCode !== self::SUCCESS) {
                return $scheduleExitCode;
            }

            return $this->call('queue:work', [
                'connection' => 'database',
                '--queue' => 'default',
                '--stop-when-empty' => true,
                '--tries' => 1,
                '--timeout' => 45,
                '--max-time' => 50,
                '--no-interaction' => true,
            ]);
        } finally {
            $lock->release();
        }
    }
}
