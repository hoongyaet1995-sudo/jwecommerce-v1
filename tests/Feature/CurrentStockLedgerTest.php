<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\CurrentStockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CurrentStockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function balance(): int
    {
        $product = Product::create(['title' => 'Current Stock Test', 'handle' => 'current-stock-test', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-TEST', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'current-stock-test', 'kind' => 'shared',
            'created_at' => now(), 'updated_at' => now()]);

        return DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_count_and_order_effect_change_one_absolute_stock_once(): void
    {
        $id = $this->balance();
        $ledger = app(CurrentStockLedger::class);
        $this->assertNull($ledger->quantity($id));
        $this->assertSame(10, $ledger->setAbsolute($id, 10, 'first-count:test', 'First Count')['after']);
        $this->assertSame(9, $ledger->change($id, -1, 'order:test', 'Order')['after']);
        $this->assertSame(9, $ledger->change($id, -1, 'order:test', 'Order')['after']);
        $this->assertSame(9, $ledger->quantity($id));
        $this->assertDatabaseCount('inventory_current_stock_movements', 2);
    }

    public function test_uncounted_and_conflicting_replay_cannot_change_stock(): void
    {
        $id = $this->balance();
        $ledger = app(CurrentStockLedger::class);
        try {
            $ledger->change($id, -1, 'order:uncounted', 'Order');
            $this->fail('Uncounted stock was changed.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('count', strtolower($error->getMessage()));
        }
        $ledger->setAbsolute($id, 2, 'first-count:test', 'First Count');
        $this->expectException(RuntimeException::class);
        $ledger->setAbsolute($id, 3, 'first-count:test', 'First Count');
    }
}
