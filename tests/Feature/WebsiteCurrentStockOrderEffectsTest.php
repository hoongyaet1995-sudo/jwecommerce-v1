<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Admin\OrderOperationsService;
use App\Services\Inventory\CurrentStockLedger;
use App\Services\Inventory\CurrentStockSyncIntentDrain;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\WebsiteCurrentStockOrderEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

class WebsiteCurrentStockOrderEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_opening_stock_serves_website_without_first_count_and_switches_after_count(): void
    {
        config(['inventory.current_stock_only' => true]);
        foreach (['shared_pool', 'independent_stores'] as $mode) {
            DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => $mode]);
            $sku = (new StoreInventoryFixture)->sku();
            $actor = User::factory()->create(['is_admin' => true]);
            $availability = app(\App\Services\Inventory\WebsiteInventoryAvailability::class);
            $this->assertNull($availability->quantity($sku['variant']));
            app(\App\Services\Inventory\CatalogStockService::class)->setPhysical($sku['variant']->id, 10, null, 'opening-'.$mode, $actor->id);
            $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
            $this->assertSame(10, $availability->quantity($sku['variant']->fresh()));
            $this->assertSame(10, $sku['variant']->fresh()->stock);
            try {
                app(\App\Services\Inventory\CurrentStockOrderLine::class)->apply('website', 'oversell-'.$mode, 'oversell',
                    $sku['variant']->id, $balance['id'], 11, 11, 0, 'oversell-'.$mode);
                $this->fail('Opening inventory allowed overselling.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Catalog opening stock is insufficient.', $error->getMessage());
            }
            $this->assertSame(10, $availability->quantity($sku['variant']->fresh()));
            $order = Order::create(['number' => 'OPENING-'.$mode, 'email' => 'stock@example.test', 'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 200, 'total' => 200]);
            $item = $order->items()->create(['product_variant_id' => $sku['variant']->id, 'sku' => $sku['variant']->sku, 'title' => 'Test SKU', 'unit_price' => 100, 'quantity' => 2, 'line_total' => 200]);
            $effects = app(WebsiteCurrentStockOrderEffects::class);
            $effects->debit($order);
            $effects->debit($order);
            $this->assertSame(8, $availability->quantity($sku['variant']->fresh()));
            $this->assertNull(DB::table('inventory_balances')->where('id', $balance['id'])->value('current_stock'));
            $this->assertSame(0, DB::table('inventory_current_stock_sync_intents')->where('inventory_balance_id', $balance['id'])->count());
            $effects->restore($order, [$item->id => 1], 'refund:1');
            $this->assertSame(9, $availability->quantity($sku['variant']->fresh()));
            app(CurrentStockLedger::class)->setAbsolute($balance['id'], 6, 'first-count-'.$mode, 'First Count');
            $this->assertSame(6, $availability->quantity($sku['variant']->fresh()));
            $effects->restore($order, $effects->remaining($order), 'cancelled');
            $this->assertSame(7, $availability->quantity($sku['variant']->fresh()));
        }
    }

    public function test_website_order_debits_at_creation_and_restores_only_the_affected_units(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $sku = (new StoreInventoryFixture)->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        app(CurrentStockLedger::class)->setAbsolute($balance['id'], 10, 'test:first-count', 'First Count');
        $order = Order::query()->create(['number' => 'JW-CURRENT-1', 'email' => 'stock@example.test',
            'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 200,
            'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 200]);
        $item = $order->items()->create(['product_variant_id' => $sku['variant']->id,
            'sku' => $sku['variant']->sku, 'title' => 'Test SKU', 'variant_title' => 'Default',
            'unit_price' => 100, 'quantity' => 2, 'line_total' => 200]);
        $effects = app(WebsiteCurrentStockOrderEffects::class);
        $effects->debit($order);
        $effects->debit($order);
        $this->assertSame(8, app(CurrentStockLedger::class)->quantity($balance['id']));
        $this->assertSame(8, (int) DB::table('product_variants')->where('id', $sku['variant']->id)->value('stock'));
        $this->assertDatabaseCount('inventory_current_stock_sync_intents', 1);
        $effects->restore($order, [$item->id => 1], 'refund:1');
        $this->assertSame(9, app(CurrentStockLedger::class)->quantity($balance['id']));
        $effects->restore($order, $effects->remaining($order), 'cancelled');
        $this->assertSame(10, app(CurrentStockLedger::class)->quantity($balance['id']));
        $this->assertDatabaseCount('inventory_current_stock_sync_intents', 3);
        $this->assertSame([], $effects->remaining($order));
        $this->assertSame(['queued' => 1, 'superseded' => 2, 'pending' => 0],
            app(CurrentStockSyncIntentDrain::class)->run());
        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'product_variant_id' => $sku['variant']->id, 'target_quantity' => 10,
            'status' => 'confirmed',
        ]);
    }

    public function test_partial_refund_requires_selected_items_and_full_refund_restores_remaining_stock(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $sku = (new StoreInventoryFixture)->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        app(CurrentStockLedger::class)->setAbsolute($balance['id'], 5, 'refund:first-count', 'First Count');
        $order = Order::query()->create(['number' => 'JW-REFUND-1', 'email' => 'refund@example.test',
            'currency' => 'MYR', 'status' => 'paid', 'subtotal' => 200,
            'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 200]);
        $item = $order->items()->create(['product_variant_id' => $sku['variant']->id,
            'sku' => $sku['variant']->sku, 'title' => 'Test SKU', 'variant_title' => 'Default',
            'unit_price' => 100, 'quantity' => 2, 'line_total' => 200]);
        $order->payments()->create(['gateway' => 'simulated', 'status' => 'paid']);
        app(WebsiteCurrentStockOrderEffects::class)->debit($order);
        $actor = User::factory()->create();
        $operations = app(OrderOperationsService::class);
        try {
            $operations->refund($order, 100, 'Partial', $actor->id);
            $this->fail('Partial refund without an item quantity was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('restock_items', $exception->errors());
        }
        $this->assertSame(3, app(CurrentStockLedger::class)->quantity($balance['id']));
        $this->assertDatabaseCount('order_refunds', 0);
        $operations->refund($order, 200, 'Full refund', $actor->id);
        $this->assertSame(5, app(CurrentStockLedger::class)->quantity($balance['id']));
        $this->assertSame([], app(WebsiteCurrentStockOrderEffects::class)->remaining($order));
    }
}
