<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Inventory\StoreManagement\CountMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrentStockCountMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_count_then_intake_and_audit_use_current_stock_only(): void
    {
        config(['inventory.current_stock_only' => true]);
        $product = Product::create(['title' => 'Count Mutation', 'handle' => 'count-mutation', 'base_price' => 1]);
        $variant = $product->variants()->create(['sku' => 'CS-COUNT', 'title' => 'Test', 'price' => 1, 'stock' => 0]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key' => 'count-mutation', 'kind' => 'shared',
            'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('inventory_balances')->insertGetId(['inventory_pool_id' => $pool,
            'product_variant_id' => $variant->id, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $mutation = app(CountMutation::class);
        $first = $mutation->preview('count', DB::table('inventory_balances')->find($id), ['physical_count' => 5]);
        $this->assertSame(5, $first['after']['current_stock']);
        $mutation->apply(DB::table('inventory_balances')->find($id), $first['after'], 'count:test');
        $intake = $mutation->preview('intake', DB::table('inventory_balances')->find($id), ['quantity' => 2]);
        $this->assertSame(7, $intake['after']['current_stock']);
        $mutation->apply(DB::table('inventory_balances')->find($id), $intake['after'], 'intake:test');
        $audit = $mutation->preview('correction', DB::table('inventory_balances')->find($id), ['quantity_delta' => -3]);
        $this->assertSame(4, $audit['after']['current_stock']);
        $this->assertNull(DB::table('inventory_balances')->where('id', $id)->value('physical_stock'));
    }
}
