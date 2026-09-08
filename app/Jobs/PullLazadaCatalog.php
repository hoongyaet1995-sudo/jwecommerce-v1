<?php

namespace App\Jobs;

use App\Services\Marketplace\LazadaCatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Processes one fixed Lazada catalog page. It only captures provider observations. */
class PullLazadaCatalog implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1200;
    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public int $expectedPage = 0)
    {
        $this->onConnection('marketplace')->onQueue('marketplace');
    }

    public function handle(LazadaCatalogService $catalog): void
    {
        if (!DB::table('marketplace_catalog_runs')->where('id', $this->runId)->where('status', 'queued')
            ->where('pages_completed', $this->expectedPage)->update(['status'=>'running', 'updated_at'=>now()])) {
            return;
        }
        if (config('marketplace.operations_enabled') !== true && config('marketplace.read_operations_enabled') !== true) {
            $this->failRun('marketplace_operations_disabled');
            return;
        }

        try {
            $run = DB::table('marketplace_catalog_runs')->find($this->runId);
            $page = $catalog->pullPage($run->store_id, $run->next_offset);
            if ($page['has_more'] && $page['next_offset'] > 5000) {
                $this->failRun('lazada_offset_limit_reached');
                return;
            }
            DB::transaction(function () use ($run, $page) {
                DB::table('marketplace_catalog_runs')->where('id', $run->id)->where('status', 'running')
                    ->where('pages_completed', $this->expectedPage)->update([
                        'status'=>$page['has_more'] ? 'queued' : 'succeeded',
                        'next_offset'=>$page['next_offset'], 'pages_completed'=>$run->pages_completed + 1,
                        'items_processed'=>$run->items_processed + $page['items'],
                        'variants_processed'=>$run->variants_processed + $page['variants'],
                        'finished_at'=>$page['has_more'] ? null : now(), 'updated_at'=>now(),
                    ]);
                if ($page['has_more']) {
                    $queue = config('queue.connections.marketplace');
                    if (($queue['driver'] ?? null) !== 'database' || ($queue['connection'] ?? config('database.default')) !== DB::connection()->getName()) {
                        throw new RuntimeException('Catalog continuation requires the application database queue.');
                    }
                    self::dispatch($run->id, $run->pages_completed + 1)->beforeCommit();
                }
            });
        } catch (Throwable) {
            $this->failRun('lazada_catalog_page_failed');
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        $this->failRun('lazada_catalog_page_failed');
    }

    private function failRun(string $code): void
    {
        DB::table('marketplace_catalog_runs')->where('id', $this->runId)->whereIn('status', ['queued', 'running'])
            ->where('pages_completed', $this->expectedPage)->update([
                'status'=>'failed', 'error_code'=>$code, 'finished_at'=>now(), 'updated_at'=>now(),
            ]);
    }
}
