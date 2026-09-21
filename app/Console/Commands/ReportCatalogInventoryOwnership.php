<?php

namespace App\Console\Commands;

use App\Services\Inventory\CatalogInventoryState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReportCatalogInventoryOwnership extends Command
{
    protected $signature = 'inventory:catalog-ownership-report';
    protected $description = 'Report catalog inventory ownership without changing inventory data.';

    public function handle(CatalogInventoryState $states): int
    {
        $rows = DB::table('product_variants')->orderBy('id')->get(['id', 'sku'])->map(function (object $variant) use ($states): array {
            $state = $states->snapshot((int) $variant->id);

            return [
                $variant->id,
                $variant->sku,
                $state['verified'] ? 'verified' : ($state['repair_required'] ? 'ambiguous' : ($state['initialized'] ? 'initialized' : 'catalog-only')),
                $state['physical'] ?? '—',
            ];
        });

        $this->table(['Variant ID', 'SKU', 'Ownership', 'Physical'], $rows->all());

        return self::SUCCESS;
    }
}
