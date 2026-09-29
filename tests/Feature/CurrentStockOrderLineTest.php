<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Inventory\CurrentStockOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CurrentStockOrderLineTest extends TestCase
{
    use RefreshDatabase;

    private function stock(): array
    {
        $product = Product::create(['title' => 'Order Stock Test', 'handle' => 'order-stock-test', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-ORDER', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'order-stock-test', 'kind' => 'shared',
            'created_at' => now(), 'updated_at' => now()]);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentStockLedger::class)->setAbsolute($balance, 10, 'count:order-stock', 'First Count');

        return [$variant->id, $balance];
    }

    public function test_order_debits_once_and_cancel_then_refund_restore_once(): void
    {
        [$variant, $balance] = $this->stock();
        $orders = app(CurrentStockOrderLine::class);
        $args = ['website', 'ORDER-1', 'ITEM-1', $variant, $balance, 2];
        $this->assertSame(8, $orders->apply(...[...$args, 2, 0, 'order:placed'])['current_stock']);
        $this->assertSame(8, $orders->apply(...[...$args, 2, 0, 'order:placed'])['current_stock']);
        $this->assertSame(9, $orders->apply(...[...$args, 2, 1, 'order:cancel-one'])['current_stock']);
        $this->assertSame(9, $orders->apply(...[...$args, 2, 1, 'order:refund-one'])['current_stock']);
        $this->assertSame(10, $orders->apply(...[...$args, 2, 2, 'order:refund-two'])['current_stock']);
        $this->assertDatabaseCount('inventory_current_stock_order_lines', 1);
        $this->assertDatabaseCount('inventory_current_stock_movements', 4);
    }

    public function test_terminal_first_seen_order_cannot_create_positive_stock(): void
    {
        [$variant, $balance] = $this->stock();
        $orders = app(CurrentStockOrderLine::class);
        $this->assertSame(10, $orders->apply('marketplace:1', 'OLD-ORDER', 'ITEM-1', $variant, $balance, 1,
            0, 0, 'old:cancelled', true)['current_stock']);
        $this->expectException(RuntimeException::class);
        $orders->apply('marketplace:1', 'OLD-ORDER', 'ITEM-1', $variant, $balance, 1,
            1, 0, 'old:reopened');
    }

    public function test_conflicting_owner_and_over_credit_are_rejected_without_stock_change(): void
    {
        [$variant, $balance] = $this->stock();
        $orders = app(CurrentStockOrderLine::class);
        $orders->apply('website', 'ORDER-2', 'ITEM-1', $variant, $balance, 1, 1, 0, 'order:two');
        try {
            $orders->apply('website', 'ORDER-2', 'ITEM-1', $variant, $balance, 2, 2, 0, 'order:tamper');
            $this->fail('Changed order identity was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('identity', strtolower($error->getMessage()));
        }
        try {
            $orders->apply('website', 'ORDER-2', 'ITEM-1', $variant, $balance, 1, 1, 2, 'order:over-credit');
            $this->fail('Over-credit was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('quantit', strtolower($error->getMessage()));
        }
        $this->assertSame(9, app(CurrentStockLedger::class)->quantity($balance));
    }
}
