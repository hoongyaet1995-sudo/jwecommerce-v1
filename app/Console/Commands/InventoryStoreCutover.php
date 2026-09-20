<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class InventoryStoreCutover extends Command
{
    protected $signature = 'inventory:store-cutover
        {--dry-run : Write a record-level manifest without changing data}
        {--output= : Dry-run manifest path}
        {--manifest= : Approved manifest path for apply}
        {--environment= : Explicit Laravel environment for apply}';

    protected $description = 'Prepare or apply the targeted warehouse-to-store inventory cutover';

    public function handle(): int
    {
        if ($this->option('dry-run')) return $this->dryRun();
        return $this->apply();
    }

    private function dryRun(): int
    {
        $payload = $this->payload();
        $manifest = ['schema_version' => 1, 'generated_at' => now()->toIso8601String(), 'payload' => $payload,
            'legacy' => $payload['legacy'], 'hash' => $this->hash($payload)];
        $path = $this->option('output') ?: storage_path('app/private/inventory-store-cutover.json');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info("Dry-run manifest written: {$path}");
        $this->line('Manifest hash: '.$manifest['hash']);
        if ($payload['blockers']) $this->warn('Apply is blocked until listed warehouse balances are reconciled.');
        return self::SUCCESS;
    }

    private function apply(): int
    {
        $path = (string) $this->option('manifest');
        $environment = (string) $this->option('environment');
        if ($path === '' || ! is_file($path) || $environment === '' || $environment !== app()->environment()) {
            $this->error('Apply requires --manifest and an explicit --environment matching the current Laravel environment.');
            return self::FAILURE;
        }
        $approved = json_decode(file_get_contents($path), true);
        if (! is_array($approved) || ($approved['schema_version'] ?? null) !== 1 || ! isset($approved['hash'])) {
            $this->error('The approved cutover manifest is invalid.');
            return self::FAILURE;
        }
        $current = $this->payload();
        if (! hash_equals((string) $approved['hash'], $this->hash($current))) {
            $this->error('The approved manifest no longer matches current inventory records; run a new dry-run.');
            return self::FAILURE;
        }
        if ($current['blockers']) {
            $this->error('Cutover is blocked by legacy warehouse balances. Review the manifest blockers first.');
            return self::FAILURE;
        }

        DB::transaction(function (): void {
            DB::table('inventory_fulfillment_routes')->where('is_enabled', true)->update([
                'is_enabled' => false, 'version' => DB::raw('version + 1'), 'updated_at' => now(),
            ]);
            DB::table('inventory_warehouses')->where('status', 'active')->update([
                'status' => 'inactive', 'is_active' => false, 'version' => DB::raw('version + 1'), 'updated_at' => now(),
            ]);
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            DB::table('inventory_configuration')->where('id', 1)->update([
                'operating_mode' => 'independent_stores', 'default_warehouse_id' => null,
                'version' => (int) $configuration->version + 1,
            ]);
        }, 3);
        $this->info('Store Inventory cutover applied. Legacy warehouse writers are disabled; catalog, stores, credentials and history were preserved.');
        return self::SUCCESS;
    }

    private function payload(): array
    {
        $warehouses = DB::table('inventory_warehouses')->orderBy('id')->get(['id','code','status','is_active','version','updated_at'])
            ->map(fn ($row) => (array) $row)->all();
        $routes = DB::table('inventory_fulfillment_routes')->orderBy('id')->get(['id','channel_key','warehouse_id','is_enabled','version','updated_at'])
            ->map(fn ($row) => (array) $row)->all();
        $balances = DB::table('inventory_balances as b')->join('inventory_pools as p','p.id','=','b.inventory_pool_id')
            ->where('p.kind','warehouse')->orderBy('b.id')->get(['b.id','b.inventory_pool_id','b.product_variant_id','b.physical_stock','b.reserved_stock','b.version','b.updated_at'])
            ->map(fn ($row) => (array) $row)->all();
        $blockers = array_values(array_map(fn ($row) => ['balance_id'=>$row['id'],'reason'=>'legacy_warehouse_balance_requires_review'],
            array_filter($balances, fn ($row) => $row['physical_stock'] === null || $row['reserved_stock'] === null || (int) $row['physical_stock'] !== 0 || (int) $row['reserved_stock'] !== 0)));
        return ['configuration' => (array) DB::table('inventory_configuration')->where('id',1)->first(['operating_mode','default_warehouse_id','version']),
            'legacy' => ['warehouses'=>$warehouses,'routes'=>$routes,'balances'=>$balances], 'blockers'=>$blockers,
            'preserved_counts' => ['products'=>DB::table('products')->count(),'marketplace_stores'=>DB::table('marketplace_stores')->count()]];
    }

    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
