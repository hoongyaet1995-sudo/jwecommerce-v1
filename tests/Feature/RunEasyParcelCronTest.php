<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    }

    public function test_cron_runs_due_scheduler_then_drains_database_queue_with_a_lock(): void
    {
        Schedule::job(new MarkEasyParcelCronProcessed)
            ->everyMinute();

        $this->artisan('easyparcel:cron')
            ->assertExitCode(0);

        $this->assertTrue(Cache::get('easyparcel-cron-test-processed', false));
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_overlapping_cron_exits_cleanly_without_double_processing(): void
    {
        Schedule::job(new MarkEasyParcelCronProcessed)
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
