<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunMarketplaceCronTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array', 'marketplace.operations_enabled'=>false,
            'marketplace.read_operations_enabled'=>true]);
    }

    public function test_marketplace_cron_processes_one_job_only_and_leaves_other_queues_untouched(): void
    {
        foreach (['marketplace', 'marketplace', 'easyparcel', 'default'] as $queue) {
            Queue::connection('marketplace')->pushOn($queue, new MarkMarketplaceCronExecution);
        }
        $this->artisan('marketplace:cron')->assertExitCode(0);
        $this->assertSame(1, Cache::get('marketplace-cron-executions'));
        $this->assertSame(1, DB::table('jobs')->where('queue', 'marketplace')->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'easyparcel')->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'default')->count());
        // A subsequent invocation can acquire the released lock and process the next page.
        $this->artisan('marketplace:cron')->assertExitCode(0);
        $this->assertSame(2, Cache::get('marketplace-cron-executions'));
    }

    public function test_marketplace_cron_does_not_consume_work_when_disabled(): void
    {
        config(['marketplace.read_operations_enabled'=>false]);
        Queue::connection('marketplace')->pushOn('marketplace', new MarkMarketplaceCronExecution);
        $this->artisan('marketplace:cron')->assertExitCode(0);
        $this->assertNull(Cache::get('marketplace-cron-executions'));
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_marketplace_cron_does_not_process_during_an_existing_lease(): void
    {
        Queue::connection('marketplace')->pushOn('marketplace', new MarkMarketplaceCronExecution);
        $lock = Cache::lock('marketplace:cron', 1500);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('marketplace:cron')->assertExitCode(0);
            $this->assertNull(Cache::get('marketplace-cron-executions'));
            $this->assertDatabaseCount('jobs', 1);
        } finally {
            $lock->release();
        }
    }
}

class MarkMarketplaceCronExecution implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('marketplace-cron-executions', Cache::get('marketplace-cron-executions', 0)+1, 60);
    }
}
