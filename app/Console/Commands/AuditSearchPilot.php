<?php

namespace App\Console\Commands;

use App\Services\Search\SearchPilotAudit;
use Illuminate\Console\Command;

class AuditSearchPilot extends Command
{
    protected $signature = 'search:audit';

    protected $description = 'Read-only business identity and public search readiness report; does not call AI or claim index inclusion';

    public function handle(SearchPilotAudit $audit): int
    {
        $this->line(json_encode($audit->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
