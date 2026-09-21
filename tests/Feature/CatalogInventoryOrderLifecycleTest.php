<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\CatalogInventoryState;
use App\Services\Inventory\CatalogStockService;
use App\Services\Inventory\StoreManagement\OrderInventoryRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class CatalogInventoryOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialized_website_stock_reserves_and_cancels_without_first_count(): void
    {
        [$variantId, $balanceId] = $this->initializedSku(20);
        $router = app(OrderInventoryRouter::class);
        $router->register('website', 'line-cancel', $variantId, 3);

        $reserved = $router->apply('website', 'line-cancel', 'line-cancel-reserved', 'reserved', 3);
        $replayed = $router->apply('website', 'line-cancel', 'line-cancel-reserved', 'reserved', 3);
        $cancelled = $router->apply('website', 'line-cancel', 'line-cancel-cancelled', 'cancelled', 3);

        $this->assertSame(17, $reserved['available']);
        $this->assertSame($reserved, $replayed);
        $this->assertSame(20, $cancelled['physical']);
        $this->assertSame(0, $cancelled['reserved']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balanceId, 'physical_stock' => 20, 'reserved_stock' => 0]);
        $this->assertFalse(app(CatalogInventoryState::class)->snapshot($variantId)['verified']);
    }

    public function test_shipment_reduces_physical_and_inspected_return_restores_it(): void
    {
        [$variantId, $balanceId] = $this->initializedSku(20);
        $router = app(OrderInventoryRouter::class);
        $router->register('website', 'line-ship', $variantId, 3);
        $router->apply('website', 'line-ship', 'line-ship-reserved', 'reserved', 3);
        $shipped = $router->apply('website', 'line-ship', 'line-ship-shipped', 'shipped', 3);
        $refunded = $router->apply('website', 'line-ship', 'line-ship-refunded', 'refunded', 3);
        $returned = $router->apply('website', 'line-ship', 'line-ship-returned', 'returned_inspected', 3);

        $this->assertSame(17, $shipped['physical']);
        $this->assertSame(17, $refunded['physical']);
        $this->assertSame(20, $returned['physical']);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balanceId, 'physical_stock' => 20, 'reserved_stock' => 0]);
    }

    /** @return array{int,int} */
    private function initializedSku(int $quantity): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update([
            'operating_mode' => 'independent_stores', 'accounting_enabled' => true,
        ]);
        $sku = (new StoreInventoryFixture)->sku();
        $actor = User::factory()->create(['is_admin' => true]);
        app(CatalogStockService::class)->setPhysical(
            $sku['variant']->id, $quantity, null, 'opening-'.$sku['variant']->id, $actor->id,
        );
        $balanceId = (int) DB::table('inventory_balances')->where('product_variant_id', $sku['variant']->id)->value('id');

        return [(int) $sku['variant']->id, $balanceId];
    }
}
