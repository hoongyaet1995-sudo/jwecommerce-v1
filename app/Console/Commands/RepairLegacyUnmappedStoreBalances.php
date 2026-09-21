<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Inventory\StoreManagement\LegacyUnmappedStoreBalanceRepair;
use Illuminate\Console\Command;

final class RepairLegacyUnmappedStoreBalances extends Command
{
    protected $signature = 'inventory:repair-legacy-unmapped-store-balances
        {--apply : Zero safe legacy phantom balances and record reconciliations}
        {--environment= : Explicit Laravel environment required for apply}
        {--actor-id= : Super-admin user responsible for the repair}';

    protected $description = 'Inspect or repair legacy mode-transition stock assigned to unmapped marketplace stores';

    public function handle(LegacyUnmappedStoreBalanceRepair $repair): int
    {
        $inspection = $repair->inspect();
        $this->line(json_encode($inspection, JSON_UNESCAPED_SLASHES));
        if (! $this->option('apply')) {
            $this->info('Dry run only. Add --apply, --environment, and --actor-id to repair these balances.');

            return self::SUCCESS;
        }
        if ((string) $this->option('environment') !== app()->environment()) {
            $this->error('Apply requires --environment matching the current Laravel environment.');

            return self::FAILURE;
        }
        $actor = User::query()->find((int) $this->option('actor-id'));
        if (! $actor || ! $actor->is_admin || ! $actor->is_super_admin) {
            $this->error('Apply requires a valid super-admin --actor-id.');

            return self::FAILURE;
        }
        $this->line(json_encode($repair->apply($actor), JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
