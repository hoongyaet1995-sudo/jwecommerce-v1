<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function variant(): ProductVariant
    {
        $product = Product::query()->create(['title'=>'Allocation test', 'handle'=>'allocation-test', 'base_price'=>1000, 'status'=>'active']);
        return $product->variants()->create(['sku'=>'ALLOC-1', 'title'=>'Default', 'price'=>1000, 'stock'=>10, 'enabled'=>true]);
    }

    public function test_reserving_and_shipping_replays_only_remove_availability_once(): void
    {
        $variant = $this->variant();
        $service = app(StockAllocationService::class);
        $id = $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        $this->assertSame($id, $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3));
        $this->assertEquals(7, $variant->fresh()->stock);
        $service->consume($id);
        $service->consume($id);
        $this->assertSame($id, $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3));
        $this->assertEquals(7, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_allocations', ['id'=>$id, 'status'=>'consumed', 'quantity'=>3]);
        $this->assertDatabaseCount('stock_allocation_events', 2);
    }

    public function test_cancellation_releases_only_its_own_reservation_once(): void
    {
        $variant = $this->variant();
        $service = app(StockAllocationService::class);
        $first = $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        $service->reserve('marketplace_order', 13, 'line-1', $variant->id, 2);
        $service->release($first);
        $service->release($first);
        $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        $this->assertEquals(8, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_allocations', 2);
        $this->assertDatabaseCount('stock_allocation_events', 3);
    }

    public function test_consumed_stock_cannot_be_released_as_a_cancellation(): void
    {
        $variant = $this->variant();
        $service = app(StockAllocationService::class);
        $id = $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        $service->consume($id);
        try {
            $service->release($id);
            $this->fail('A shipment must not be restocked as an unshipped cancellation.');
        } catch (RuntimeException) {
            $this->assertEquals(7, $variant->fresh()->stock);
            $this->assertDatabaseCount('stock_allocation_events', 2);
        }
    }

    public function test_released_stock_cannot_be_consumed_without_a_new_review(): void
    {
        $variant = $this->variant();
        $service = app(StockAllocationService::class);
        $id = $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        $service->release($id);
        $this->expectException(RuntimeException::class);
        $service->consume($id);
    }

    public function test_existing_allocation_cannot_change_its_sku_or_quantity(): void
    {
        $variant = $this->variant();
        $other = $variant->product->variants()->create(['sku'=>'ALLOC-2', 'title'=>'Other', 'price'=>1000, 'stock'=>20]);
        $service = app(StockAllocationService::class);
        $service->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
        foreach ([[$variant->id, 4], [$other->id, 3]] as [$id, $quantity]) {
            try {
                $service->reserve('marketplace_order', 12, 'line-1', $id, $quantity);
                $this->fail('Historical allocation identity must be immutable.');
            } catch (RuntimeException) {
                $this->assertEquals(7, $variant->fresh()->stock);
                $this->assertEquals(20, $other->fresh()->stock);
            }
        }
        $this->assertDatabaseCount('stock_allocations', 1);
    }

    public function test_insufficient_or_invalid_quantities_do_not_create_partial_effects(): void
    {
        $variant = $this->variant();
        $service = app(StockAllocationService::class);
        foreach ([0, -1, 11] as $quantity) {
            try {
                $service->reserve('marketplace_order', 12, 'line-1', $variant->id, $quantity);
                $this->fail('Invalid allocation should be rejected.');
            } catch (RuntimeException) {
                $this->assertEquals(10, $variant->fresh()->stock);
                $this->assertDatabaseCount('stock_allocations', 0);
            }
        }
    }

    public function test_outer_order_transaction_rolls_back_inventory_and_ledger_together(): void
    {
        $variant = $this->variant();
        try {
            DB::transaction(function () use ($variant) {
                app(StockAllocationService::class)->reserve('marketplace_order', 12, 'line-1', $variant->id, 3);
                throw new RuntimeException('Second order line is unmapped.');
            });
        } catch (RuntimeException) {
            $this->assertEquals(10, $variant->fresh()->stock);
            $this->assertDatabaseCount('stock_allocations', 0);
            $this->assertDatabaseCount('stock_allocation_events', 0);
        }
    }
}
