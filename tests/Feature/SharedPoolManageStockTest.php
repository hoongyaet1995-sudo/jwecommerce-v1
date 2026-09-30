<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedPoolManageStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_count_can_open_one_shared_sku_ledger_once_and_read_its_movements(): void
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $product = Product::create(['title' => 'Shared Count', 'handle' => 'shared-count', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'SHARED-COUNT', 'title' => 'Default', 'price' => 1, 'stock' => 0]);
        $this->actingAs($admin);
        $ledgerUrl = "/admin/api/inventory/store-management/shared-skus/{$variant->id}/ledger";
        $this->getJson($ledgerUrl)->assertOk()->assertJsonPath('current_stock', null)->assertJsonCount(0, 'movements');

        $payload = ['kind' => 'count', 'variant_id' => $variant->id, 'channel_key' => 'website',
            'physical_count' => 7, 'reason' => 'Opening Shared Pool First Count'];
        $first = $this->postJson('/admin/api/inventory/store-management/reviews', $payload)->assertCreated()->json();
        $second = $this->postJson('/admin/api/inventory/store-management/reviews', $payload)->assertCreated()->json();
        $this->postJson('/admin/api/inventory/store-management/reviews/'.$first['id'].'/confirm', [
            'operation_key' => 'shared-opening-count-1',
        ])->assertOk();

        $this->getJson($ledgerUrl)->assertOk()->assertJsonPath('current_stock', 7)
            ->assertJsonPath('movements.0.delta', 7)->assertJsonPath('movements.0.current_after', 7)
            ->assertJsonCount(1, 'movements');
        $this->postJson('/admin/api/inventory/store-management/reviews/'.$second['id'].'/confirm', [
            'operation_key' => 'shared-opening-count-2',
        ])->assertUnprocessable();
        $this->postJson('/admin/api/inventory/store-management/reviews', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('inventory_current_stock_movements', 1);
    }
}
