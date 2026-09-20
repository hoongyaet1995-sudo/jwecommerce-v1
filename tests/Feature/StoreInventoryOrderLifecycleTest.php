<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Services\CheckoutService;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\OrderInventoryRouter;
use App\Services\Inventory\WebsiteInventoryAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{router:OrderInventoryRouter,balance:array<string,mixed>,line:string} */
    private function line(string $channel = 'website', int $physical = 10, int $quantity = 4): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        if ($channel !== 'website') {
            $store = $fixture->store('shopee', 'ORDER');
            $channel = 'marketplace:'.$store->id;
        }
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, $channel);
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => $physical, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $router = app(OrderInventoryRouter::class);
        $line = 'order-line-'.bin2hex(random_bytes(4));
        $router->register($channel, $line, $sku['variant']->id, $quantity);

        return ['router' => $router, 'balance' => $balance, 'line' => $line, 'channel' => $channel];
    }

    public function test_unpaid_order_reserves_then_partial_ship_and_cancel_conserve_stock(): void
    {
        $context = $this->line();
        $router = $context['router'];
        $router->apply($context['channel'], $context['line'], 'reserve-1', 'reserved', 4);
        $this->assertBalance($context['balance']['id'], 10, 4);
        $router->apply($context['channel'], $context['line'], 'ship-1', 'shipped', 2);
        $this->assertBalance($context['balance']['id'], 8, 2);
        $router->apply($context['channel'], $context['line'], 'cancel-1', 'cancelled', 2);
        $this->assertBalance($context['balance']['id'], 8, 0);

        $router->apply($context['channel'], $context['line'], 'late-reserve', 'reserved', 4);
        $this->assertBalance($context['balance']['id'], 8, 0);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 4);
    }

    public function test_refund_does_not_restock_but_reviewed_resellable_return_does_once(): void
    {
        $context = $this->line();
        $router = $context['router'];
        $router->apply($context['channel'], $context['line'], 'reserve', 'reserved', 4);
        $router->apply($context['channel'], $context['line'], 'ship', 'shipped', 2);
        $router->apply($context['channel'], $context['line'], 'refund', 'refunded', 1);
        $this->assertBalance($context['balance']['id'], 8, 2);
        $first = $router->apply($context['channel'], $context['line'], 'return-inspected', 'returned_inspected', 1);
        $second = $router->apply($context['channel'], $context['line'], 'return-inspected', 'returned_inspected', 1);
        $this->assertSame($first, $second);
        $this->assertBalance($context['balance']['id'], 9, 2);
    }

    public function test_insufficient_remote_stock_rejects_without_partial_reservation(): void
    {
        $context = $this->line('remote', 2, 4);
        try {
            $context['router']->apply($context['channel'], $context['line'], 'remote-reserve', 'reserved', 4);
            $this->fail('Remote shortage was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('JW inventory is insufficient for this store account.', $exception->getMessage());
        }
        $this->assertBalance($context['balance']['id'], 2, 0);
        $this->assertDatabaseCount('inventory_store_order_events', 0);
    }

    public function test_event_key_replay_is_idempotent_and_mismatched_payload_is_rejected(): void
    {
        $context = $this->line();
        $first = $context['router']->apply($context['channel'], $context['line'], 'event-1', 'reserved', 4);
        $second = $context['router']->apply($context['channel'], $context['line'], 'event-1', 'reserved', 4);
        $this->assertSame($first, $second);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inventory event key conflicts with another payload.');
        $context['router']->apply($context['channel'], $context['line'], 'event-1', 'cancelled', 1);
    }

    public function test_managed_jw_storefront_availability_comes_from_the_jw_ledger(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 5, 'reserved_stock' => 2, 'safety_stock' => 1, 'counted_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')->update(['management_status' => 'managed']);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update(['stock' => 999]);

        $this->assertSame(2, app(WebsiteInventoryAvailability::class)->quantity($sku['variant']->fresh()));
    }

    public function test_cart_rejects_quantity_above_managed_jw_storefront_atp(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 5, 'reserved_stock' => 2, 'safety_stock' => 1, 'counted_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')->update(['management_status' => 'managed']);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update(['stock' => 999, 'enabled' => true]);

        $this->post('/cart/items', ['variant_id' => $sku['variant']->id, 'quantity' => 3])
            ->assertSessionHasErrors('quantity');
        $this->assertSame([], session('cart', []));
    }

    public function test_checkout_rejects_quantity_above_managed_jw_storefront_atp(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 2, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')->update(['management_status' => 'managed']);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update(['stock' => 999, 'enabled' => true]);
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard', 'name' => 'Standard', 'zoneRegion' => 'all', 'logisticType' => 'all',
            'calculationMode' => 'per_order', 'rateType' => 'flat', 'tiers' => [], 'flatRateCost' => 0,
            'freeShippingThreshold' => 0, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true, 'bankAccounts' => [['id' => 'bank', 'bankName' => 'Test', 'accountNumber' => '1', 'isPrimary' => true, 'isActive' => true]],
        ]]]);
        $this->startSession();

        try {
            app(CheckoutService::class)->checkout('buyer@example.test', collect([[
                'variant' => $sku['variant']->fresh(), 'quantity' => 3,
            ]]), null, ['first_name' => 'A', 'last_name' => 'B', 'line_1' => '1 Test', 'city' => 'KL',
                'postal_code' => '50000', 'country' => 'MY'], 'bank_transfer', 'standard', ['code' => 'MYR', 'exchange_rate' => 1]);
            $this->fail('Checkout ignored managed JW ATP.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Requested quantity is unavailable.', $exception->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unpaid_checkout_reserves_managed_jw_ledger_without_mutating_legacy_stock(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 5, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'website')->update(['management_status' => 'managed']);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update(['stock' => 999, 'enabled' => true]);
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard', 'name' => 'Standard', 'zoneRegion' => 'all', 'logisticType' => 'all',
            'calculationMode' => 'per_order', 'rateType' => 'flat', 'tiers' => [], 'flatRateCost' => 0,
            'freeShippingThreshold' => 0, 'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true, 'bankAccounts' => [['id' => 'bank', 'bankName' => 'Test', 'accountNumber' => '1', 'isPrimary' => true, 'isActive' => true]],
        ]]]);

        $order = app(CheckoutService::class)->checkout('buyer@example.test', collect([[
            'variant' => $sku['variant']->fresh(), 'quantity' => 2,
        ]]), null, ['first_name' => 'A', 'last_name' => 'B', 'line_1' => '1 Test', 'city' => 'KL',
            'postal_code' => '50000', 'country' => 'MY'], 'bank_transfer', 'standard', ['code' => 'MYR', 'exchange_rate' => 1]);

        $this->assertSame('pending_payment', $order->status);
        $this->assertBalance($balance['id'], 5, 2);
        $this->assertSame(999, $sku['variant']->fresh()->stock);
        app(CheckoutService::class)->transition($order, 'cancelled');
        $this->assertBalance($balance['id'], 5, 0);
        $this->assertSame(999, $sku['variant']->fresh()->stock);
    }

    private function assertBalance(int $id, int $physical, int $reserved): void
    {
        $balance = DB::table('inventory_balances')->find($id);
        $this->assertSame($physical, (int) $balance->physical_stock);
        $this->assertSame($reserved, (int) $balance->reserved_stock);
    }
}
