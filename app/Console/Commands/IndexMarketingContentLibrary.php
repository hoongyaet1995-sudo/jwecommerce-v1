<?php

namespace App\Console\Commands;

use App\Services\Marketing\MarketingMediaSources;
use Illuminate\Console\Command;

class IndexMarketingContentLibrary extends Command
{
    protected $signature = 'marketing:library-index {--owner= : Index only this owner}';

    protected $description = 'Idempotently register existing Marketing Studio image/video files without generating or adopting media';

    public function handle(MarketingMediaSources $sources): int
    {
        foreach ($sources->backfill($this->option('owner') ? (int) $this->option('owner') : null) as $source => $count) {
            $this->line($source.': '.$count.' source records indexed');
        }

return self::SUCCESS;
    }
}
