<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\CurrentStockCutover;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Inventory\CurrentStockOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrentStockCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_order_is_never_redebited_after_a_reset(): void
    {
        DB::table('inventory_current_stock_cutovers')->insert([
            'id' => 'cutover-test', 'database_name' => 'isolated_test', 'backup_sha256' => str_repeat('a', 64),
            'cutover_at' => '2026-09-29 08:00:00', 'status' => 'complete', 'created_at' => now(),
        ]);
        $boundary = app(CurrentStockCutover::class);
        $this->assertTrue($boundary->isHistorical('marketplace:1', 'OLD-1', '2026-09-29 07:59:59'));
        $this->assertFalse($boundary->isHistorical('marketplace:1', 'NEW-1', '2026-09-29 08:00:01'));
        DB::table('inventory_current_stock_historical_orders')->insert([
            'channel_key' => 'marketplace:1', 'order_reference' => 'OLD-ID',
            'cutover_id' => 'cutover-test', 'created_at' => now(),
        ]);
        $this->assertTrue($boundary->isHistorical('marketplace:1', 'OLD-ID', '2026-09-29 08:00:01'));
    }

    public function test_preserved_historical_order_line_has_no_new_stock_effect(): void
    {
        DB::table('inventory_current_stock_cutovers')->insert([
            'id' => 'cutover-test', 'database_name' => 'isolated_test', 'backup_sha256' => str_repeat('a', 64),
            'cutover_at' => '2026-09-29 08:00:00', 'status' => 'complete', 'created_at' => now(),
        ]);
        $product = Product::create(['title' => 'Historical Test', 'handle' => 'historical-test', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-HIST', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'historical-test', 'kind' => 'shared',
            'created_at' => now(), 'updated_at' => now()]);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentStockLedger::class)->setAbsolute($balance, 10, 'count:historical', 'First Count');
        $result = app(CurrentStockOrderLine::class)->apply('marketplace:1', 'OLD-ORDER', 'ITEM-1',
            $variant->id, $balance, 1, 1, 0, 'historical:replay', false, '2026-09-29 07:59:59');
        $this->assertSame('historical', $result['status']);
        $this->assertSame(10, app(CurrentStockLedger::class)->quantity($balance));
    }

    public function test_pre_baseline_active_order_is_not_debited_twice_but_later_cancellation_restores_it(): void
    {
        $product = Product::create(['title' => 'Existing order test', 'handle' => 'existing-order-test', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-OLD-ORDER', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'marketplace-old-order', 'kind' => 'store',
            'created_at' => now(), 'updated_at' => now()]);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentStockLedger::class)->setAbsolute($balance, 9, 'count:old-order', 'Marketplace baseline');
        DB::table('inventory_balances')->where('id', $balance)->update(['counted_at' => '2026-09-29 10:00:00']);

        $lines = app(CurrentStockOrderLine::class);
        $active = $lines->apply('marketplace:1', 'PRE-BASELINE', 'ITEM-1', $variant->id,
            $balance, 1, 1, 0, 'old-order:active', false, '2026-09-29 09:59:00');
        $this->assertSame('historical', $active['status']);
        $this->assertSame(9, app(CurrentStockLedger::class)->quantity($balance));
        $cancelled = $lines->apply('marketplace:1', 'PRE-BASELINE', 'ITEM-1', $variant->id,
            $balance, 1, 1, 1, 'old-order:cancelled', true, '2026-09-29 09:59:00');
        $this->assertSame('applied', $cancelled['status']);
        $this->assertSame(10, app(CurrentStockLedger::class)->quantity($balance));
    }
}
