<?php

namespace App\Jobs;

use App\Services\Marketplace\ShopeeOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PullShopeeOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public int $expectedPage = 0)
    {
        $this->onConnection('marketplace')->onQueue('marketplace');
    }

    public function handle(ShopeeOrderService $orders): void
    {
        if (!DB::table('marketplace_order_runs')->where('id', $this->runId)->where('status', 'queued')
            ->where('pages_completed', $this->expectedPage)
            ->update(['status'=>'running', 'updated_at'=>now()])) return;
        if (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true) {
            DB::table('marketplace_order_runs')->where('id', $this->runId)
                ->where('status', 'running')->where('pages_completed', $this->expectedPage)
                ->update(['status'=>'failed', 'error_code'=>'marketplace_operations_disabled',
                    'finished_at'=>now(), 'updated_at'=>now()]);
            return;
        }
        $run = DB::table('marketplace_order_runs')->find($this->runId);
        try {
            $page = $orders->pullPage($run->store_id, $run->time_from, $run->time_to, $run->next_cursor ?? '');
            DB::transaction(function () use ($run, $page) {
                $end = $run->requested_time_to ?? $run->time_to;
                $nextWindow = !$page['has_more'] && $run->time_to < $end;
                $continue = $page['has_more'] || $nextWindow;
                // Deliberately overlap the boundary second; snapshots deduplicate.
                $window = $nextWindow ? ['time_from'=>$run->time_to, 'time_to'=>min($end, $run->time_to + 14 * 86400)] : [];
                DB::table('marketplace_order_runs')->where('id', $this->runId)->update([
                    'status'=>$continue ? 'queued' : 'succeeded', 'next_cursor'=>$nextWindow ? '' : $page['next_cursor'],
                    'pages_completed'=>$run->pages_completed + 1, 'orders_processed'=>$run->orders_processed + $page['orders'],
                    'finished_at'=>$continue ? null : now(), 'updated_at'=>now(),
                ] + $window);
                if ($continue) {
                    // The queue insert must participate in this page's transaction.
                    $queue = config('queue.connections.marketplace');
                    if (($queue['driver'] ?? null) !== 'database'
                        || ($queue['connection'] ?? config('database.default')) !== DB::connection()->getName()) {
                        throw new \RuntimeException('Order continuation requires the application database queue.');
                    }
                    self::dispatch($this->runId, $run->pages_completed + 1)->beforeCommit();
                }
            });
        } catch (Throwable) {
            $this->failed();
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        DB::table('marketplace_order_runs')->where('id', $this->runId)->whereIn('status', ['queued', 'running'])
            ->where('pages_completed', $this->expectedPage)
            ->update(['status'=>'failed', 'error_code'=>'order_page_failed', 'finished_at'=>now(), 'updated_at'=>now()]);
    }
}
