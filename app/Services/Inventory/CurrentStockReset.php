<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class CurrentStockReset
{
    /** Children precede parents so foreign keys remain enforced throughout the reset. */
    private const EFFECT_TABLES = [
        'inventory_current_stock_sync_intents',
        'inventory_current_stock_order_events',
        'inventory_current_stock_order_lines',
        'inventory_current_stock_movements',
        'inventory_store_sync_attempts',
        'inventory_store_order_events',
        'inventory_transfer_events',
        'inventory_allocation_movements',
        'inventory_allocation_ownership_changes',
        'stock_allocation_events',
        'inventory_return_movements',
        'inventory_rebaseline_legacy_balances',
        'inventory_rebaseline_destinations',
        'sandbox_inventory_rechecks',
        'inventory_physical_movements',
        'inventory_store_sync_tasks',
        'inventory_store_order_lines',
        'marketplace_stock_sync_tasks',
        'marketplace_order_inventory_corrections',
        'stock_allocations',
        'inventory_facility_order_activations',
        'inventory_order_activations',
        'inventory_transfers',
        'inventory_count_reviews',
        'inventory_store_operations',
        'inventory_store_reviews',
        'marketplace_facility_transfer_reviews',
        'marketplace_facility_stock_reviews',
        'marketplace_facility_ledger_migration_reviews',
        'marketplace_stock_take_order_reviews',
        'inventory_reconciliations',
        'inventory_sync_observations',
        'catalog_stock_operations',
        'basic_wms_safety_events',
        'sandbox_inventory_attempts',
        'inventory_movements',
        'inventory_mode_reviews',
        'inventory_rebaseline_epochs',
        'marketplace_order_inventory',
    ];

    public function inspect(): array
    {
        $counts = [];
        foreach (array_merge(['inventory_balances'], self::EFFECT_TABLES) as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return [
            'database' => DB::connection()->getDatabaseName(),
            'environment' => app()->environment(),
            'orders' => Schema::hasTable('orders') ? DB::table('orders')->count() : 0,
            'marketplace_orders' => Schema::hasTable('marketplace_orders') ? DB::table('marketplace_orders')->count() : 0,
            'tables' => $counts,
        ];
    }

    /** Clear the active JW stock epoch inside the caller's locked mode-switch transaction. */
    public function resetForModeSwitch(): void
    {
        $this->clearStockEffects(now());
    }

    public function execute(string $backupPath, string $backupSha256, string $expectedDatabase, string $cutoverKey, bool $allowProduction = false): array
    {
        $production = $allowProduction && app()->environment('production')
            && app()->isDownForMaintenance()
            && parse_url((string) config('app.url'), PHP_URL_HOST) === 'ecom.jwaistudio.online'
            && str_replace('\\', '/', base_path()) === '/home/u762348670/apps/jwecommerce';
        if (! (app()->environment('local') || $production) || DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Current Stock reset requires the local database or an explicit production cutover in maintenance mode.');
        }
        $database = DB::connection()->getDatabaseName();
        if ($database !== $expectedDatabase || $database === '' || strlen($cutoverKey) < 12) {
            throw new RuntimeException('Database identity or cutover key did not match.');
        }
        $realPath = realpath($backupPath);
        if (! $realPath || ! is_file($realPath) || filesize($realPath) < 1024
            || ! hash_equals(strtolower($backupSha256), hash_file('sha256', $realPath))) {
            throw new RuntimeException('A readable, SHA-256 verified database backup is required.');
        }
        $header = file_get_contents($realPath, false, null, 0, 8192);
        if ((! str_contains($header ?: '', 'MySQL dump') && ! str_contains($header ?: '', 'MariaDB dump'))
            || ! str_contains($header ?: '', 'Current Database: `'.$database.'`')) {
            throw new RuntimeException('Backup header does not identify this MySQL database.');
        }
        if (DB::table('inventory_current_stock_cutovers')->where('id', $cutoverKey)->exists()) {
            throw new RuntimeException('This cutover key has already been used.');
        }

        $before = $this->inspect();
        $cutoverAt = now();
        DB::transaction(function () use ($cutoverKey, $database, $backupSha256, $cutoverAt): void {
            DB::table('inventory_current_stock_cutovers')->insert([
                'id' => $cutoverKey, 'database_name' => $database,
                'backup_sha256' => strtolower($backupSha256), 'cutover_at' => $cutoverAt,
                'status' => 'preparing', 'created_at' => $cutoverAt,
            ]);

            if (Schema::hasTable('orders')) {
                DB::table('orders')->select('id')->orderBy('id')->chunkById(500, function ($orders) use ($cutoverKey, $cutoverAt): void {
                    DB::table('inventory_current_stock_historical_orders')->insertOrIgnore($orders->map(fn ($order) => [
                        'channel_key' => 'website', 'order_reference' => (string) $order->id,
                        'cutover_id' => $cutoverKey, 'created_at' => $cutoverAt,
                    ])->all());
                });
            }
            if (Schema::hasTable('marketplace_orders')) {
                DB::table('marketplace_orders')->select('id', 'store_id', 'external_order_id')->orderBy('id')
                    ->chunkById(500, function ($orders) use ($cutoverKey, $cutoverAt): void {
                        DB::table('inventory_current_stock_historical_orders')->insertOrIgnore($orders->map(fn ($order) => [
                            'channel_key' => 'marketplace:'.$order->store_id,
                            'order_reference' => (string) $order->external_order_id,
                            'cutover_id' => $cutoverKey, 'created_at' => $cutoverAt,
                        ])->all());
                    });
            }

            $this->clearStockEffects($cutoverAt);
            DB::table('inventory_current_stock_cutovers')->where('id', $cutoverKey)->update(['status' => 'complete']);
        });

        return ['cutover_id' => $cutoverKey, 'backup_sha256' => strtolower($backupSha256),
            'before' => $before, 'after' => $this->inspect()];
    }

    private function clearStockEffects(\DateTimeInterface $cutoverAt): void
    {
        // Preserve orders, listings, and the cutover audit; remove all JW stock effects.
        if (Schema::hasColumn('order_items', 'inventory_order_activation_id')) {
            DB::table('order_items')->whereNotNull('inventory_order_activation_id')
                ->update(['inventory_order_activation_id' => null]);
        }
        if (Schema::hasColumn('marketplace_inventory_items', 'stock_allocation_id')) {
            DB::table('marketplace_inventory_items')->whereNotNull('stock_allocation_id')
                ->update(['stock_allocation_id' => null]);
        }
        DB::table('inventory_configuration')->where('id', 1)->update(['active_rebaseline_epoch_id' => null]);
        DB::table('inventory_balances')->update(['rebaseline_epoch_id' => null]);
        foreach (self::EFFECT_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        DB::table('inventory_balances')->update([
            'current_stock' => null, 'physical_stock' => 0, 'reserved_stock' => 0,
            'safety_stock' => 0, 'counted_at' => null, 'obligations_reconciled_at' => null,
            'rebaseline_epoch_id' => null, 'version' => DB::raw('version + 1'), 'updated_at' => $cutoverAt,
        ]);
        DB::table('product_variants')->update([
            'stock' => 0, 'stock_version' => DB::raw('stock_version + 1'),
            'inventory_initialized_at' => null, 'first_physical_verified_at' => null,
            'first_physical_verification_operation' => null, 'updated_at' => $cutoverAt,
        ]);
    }
}
