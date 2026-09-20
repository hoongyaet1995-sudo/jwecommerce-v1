<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryReadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    public function test_shared_overview_counts_one_ledger_balance_once_across_all_store_accounts(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $fixture->store('shopee', 'A');
        $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 20, 'reserved_stock' => 4, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/admin/api/inventory/store-management/overview')->assertOk();
        $response->assertJsonPath('totals.known.physical', 20)
            ->assertJsonPath('totals.known.reserved', 4)
            ->assertJsonPath('totals.known.available', 16)
            ->assertJsonPath('totals.unknown_accounts', 0)
            ->assertJsonPath('mode', 'shared');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_independent_overview_sums_known_balances_and_keeps_unknown_distinct_from_zero(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $first = $fixture->store('shopee', 'A');
        $second = $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $resolver = app(AccountResolver::class);
        $website = $resolver->balance($sku['variant']->id, 'website');
        $a = $resolver->balance($sku['variant']->id, 'marketplace:'.$first->id);
        $b = $resolver->balance($sku['variant']->id, 'marketplace:'.$second->id);
        DB::table('inventory_balances')->where('id', $website['id'])->update([
            'physical_stock' => 20, 'reserved_stock' => 4, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_balances')->where('id', $a['id'])->update([
            'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/admin/api/inventory/store-management/overview?platform=shopee')->assertOk();
        $response->assertJsonPath('totals.known.physical', 0)
            ->assertJsonPath('totals.known.reserved', 0)
            ->assertJsonPath('totals.known.available', 0)
            ->assertJsonPath('totals.unknown_accounts', 1);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame([$first->id, $second->id], array_column($response->json('stores'), 'id'));
        $this->assertArrayNotHasKey('external_shop_id', $response->json('stores.0'));
        $this->assertArrayNotHasKey('access_token', $response->json('stores.0'));
    }

    public function test_history_is_paginated_and_operation_detail_contains_sanitized_saved_receipt(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 8, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $actor = $this->admin();
        $actor->permissions()->create(['permission' => 'inventory.edit']);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, [
            'kind' => 'count', 'balance_id' => $balance['id'], 'physical_count' => 9, 'reason' => 'Cycle count',
        ])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'history-count-1'])->assertOk()->json();

        $history = $this->getJson('/admin/api/inventory/store-management/history?per_page=1')->assertOk();
        $history->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $receipt['operation_id']);
        $detail = $this->getJson('/admin/api/inventory/store-management/operations/'.$receipt['operation_id'])->assertOk();
        $detail->assertJsonPath('operation_id', $receipt['operation_id'])
            ->assertJsonMissingPath('provider_receipt.access_token')
            ->assertJsonMissingPath('payload');
    }
}
