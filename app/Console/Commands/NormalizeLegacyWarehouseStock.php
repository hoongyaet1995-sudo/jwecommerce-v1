<?php

namespace App\Console\Commands;

use App\Services\Inventory\StoreManagement\LegacyWarehouseStockNormalizer;
use Illuminate\Console\Command;

final class NormalizeLegacyWarehouseStock extends Command
{
    protected $signature = 'inventory:normalize-legacy-warehouse-stock
        {--apply : Move eligible legacy warehouse balances into the JW Storefront account}
        {--environment= : Explicit Laravel environment required for apply}';

    protected $description = 'Normalize verified legacy WMS stock into Independent Stock website ownership';

    public function handle(LegacyWarehouseStockNormalizer $normalizer): int
    {
        if (! $this->option('apply')) {
            $this->info('Dry run only. Add --apply with the current --environment to normalize legacy stock.');

            return self::SUCCESS;
        }
        if ((string) $this->option('environment') !== app()->environment()) {
            $this->error('Apply requires --environment matching the current Laravel environment.');

            return self::FAILURE;
        }

        $result = $normalizer->normalize();
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
        if ($result['conflicts'] > 0) {
            $this->error('Conflicting website balances were preserved for manual review.');

            return self::FAILURE;
        }
        $this->info("Normalized {$result['moved']} SKU balance(s), conserving {$result['physical_units']} physical unit(s).");

        return self::SUCCESS;
    }
}
