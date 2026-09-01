<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunEasyParcelCron extends Command
{
    private const MINIMUM_LOCK_SECONDS = 180;

    protected $signature = 'easyparcel:cron';

    protected $description = 'Run due EasyParcel schedules and drain only EasyParcel reconciliation jobs';

    public function handle(): int
    {
        $lockSeconds = max(
            self::MINIMUM_LOCK_SECONDS,
            (int) config('services.easyparcel.cron_lock_seconds', self::MINIMUM_LOCK_SECONDS),
        );
        $lock = Cache::lock('easyparcel:cron', $lockSeconds);

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
                '--queue' => 'easyparcel',
                '--stop-when-empty' => true,
                '--tries' => 3,
                '--timeout' => 60,
                '--max-time' => 120,
                '--no-interaction' => true,
            ]);
        } finally {
            $lock->release();
        }
    }
}
