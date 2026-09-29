<?php

namespace App\Console\Commands;

use App\Services\Inventory\CurrentStockReset;
use Illuminate\Console\Command;
use Throwable;

final class ResetCurrentStockLedger extends Command
{
    protected $signature = 'inventory:reset-current-stock
        {--execute : Apply the local ledger reset after a dry run and verified backup}
        {--database= : Exact local database name}
        {--production : Allow the approved production cutover while maintenance mode is active}
        {--backup= : Full path to a MySQL dump made with --databases}
        {--sha256= : SHA-256 of the backup file}
        {--cutover= : Unique cutover key of at least 12 characters}';

    protected $description = 'Inspect or reset only local JW inventory effects for a fresh Current Stock First Count';

    public function handle(CurrentStockReset $reset): int
    {
        if (! $this->option('execute')) {
            $this->line(json_encode($reset->inspect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->warn('Dry run only. No data was changed.');
            return self::SUCCESS;
        }

        try {
            $receipt = $reset->execute(
                (string) $this->option('backup'), (string) $this->option('sha256'),
                (string) $this->option('database'), (string) $this->option('cutover'),
                (bool) $this->option('production')
            );
            $this->line(json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
