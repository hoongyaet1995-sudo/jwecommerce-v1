<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

final class RunEasyParcelCronTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('queue.default', 'database');
        Cache::forget('easyparcel-cron-test-processed');
        Cache::forget('easyparcel-cron-lease-expired-during-worker');
    }

    public function test_cron_runs_due_easyparcel_scheduler_and_drains_only_the_easyparcel_queue(): void
    {
        Schedule::job((new MarkEasyParcelCronProcessed)->onQueue('easyparcel'))
            ->everyMinute();

        $this->artisan('easyparcel:cron')
            ->assertExitCode(0);

        $this->assertTrue(Cache::get('easyparcel-cron-test-processed', false));
        $this->assertSame(0, DB::table('jobs')
            ->where('payload', 'like', '%MarkEasyParcelCronProcessed%')
            ->count());
    }

    public function test_overlapping_cron_exits_cleanly_without_double_processing(): void
    {
        Schedule::job((new MarkEasyParcelCronProcessed)->onQueue('easyparcel'))
            ->everyMinute();

        $lock = Cache::lock('easyparcel:cron', 55);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('easyparcel:cron')
                ->expectsOutputToContain('already running')
                ->assertExitCode(0);

            $this->assertFalse(Cache::get('easyparcel-cron-test-processed', false));
            $this->assertSame(0, DB::table('jobs')->count());
        } finally {
            $lock->release();
        }
    }

    public function test_cron_keeps_its_lock_while_draining_easyparcel_work(): void
    {
        config()->set('services.easyparcel.cron_lock_seconds', 55);

        Schedule::job((new ProbeEasyParcelCronLeaseNearSafetyBoundary)->onQueue('easyparcel'))
            ->everyMinute();

        $this->artisan('easyparcel:cron')
            ->assertExitCode(0);

        $this->assertFalse(Cache::get('easyparcel-cron-lease-expired-during-worker'));
        $this->assertSame(0, DB::table('jobs')
            ->where('payload', 'like', '%ProbeEasyParcelCronLeaseNearSafetyBoundary%')
            ->count());
    }

    public function test_cron_never_drains_default_queue_jobs_such_as_notification_delivery(): void
    {
        Schedule::job(new MarkDefaultQueueUntouched)
            ->everyMinute();

        $this->artisan('easyparcel:cron')
            ->assertExitCode(0);

        $this->assertFalse(Cache::get('easyparcel-cron-default-queue-processed', false));
        $this->assertSame(1, DB::table('jobs')
            ->where('payload', 'like', '%MarkDefaultQueueUntouched%')
            ->count());
    }

    public function test_easyparcel_schedule_keeps_tracking_at_fifteen_minutes_and_runs_reconciliation_every_minute(): void
    {
        $events = collect(Schedule::events());

        $tracking = $events->first(fn ($event): bool => str_contains($event->description, 'SyncEasyParcelTracking'));
        $reconciliation = $events->first(fn ($event): bool => str_contains($event->description, 'SweepEasyParcelReconciliation'));

        $this->assertNotNull($tracking);
        $this->assertNotNull($reconciliation);
        $this->assertSame('*/15 * * * *', $tracking->expression);
        $this->assertSame('* * * * *', $reconciliation->expression);
    }
}

final class MarkEasyParcelCronProcessed implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cache::put('easyparcel-cron-test-processed', true, 60);
    }
}

final class ProbeEasyParcelCronLeaseNearSafetyBoundary implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $originalNow = Carbon::getTestNow();

        try {
            Carbon::setTestNow(Carbon::now()->addSeconds(179));
            $competingLock = Cache::lock('easyparcel:cron', 180);
            $acquired = $competingLock->get();

            Cache::put('easyparcel-cron-lease-expired-during-worker', $acquired, 60);

            if ($acquired) {
                $competingLock->release();
            }
        } finally {
            Carbon::setTestNow($originalNow);
        }
    }
}

final class MarkDefaultQueueUntouched implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cache::put('easyparcel-cron-default-queue-processed', true, 60);
    }
}
