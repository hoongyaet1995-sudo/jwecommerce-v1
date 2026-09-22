<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'TRANSFER');
        $source = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        $destination = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $source['id'])->update([
            'physical_stock' => 10, 'reserved_stock' => 3, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_balances')->where('id', $destination['id'])->update([
            'physical_stock' => 4, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_store_accounts')->where('account_key', 'marketplace:'.$store->id)
            ->update(['management_status' => 'managed']);
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        return compact('actor', 'source', 'destination', 'store');
    }

    public function test_reviewed_transfer_conserves_physical_stock_and_preserves_reservations(): void
    {
        $c = $this->context();
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($c['actor'])->postJson($url, [
            'kind' => 'transfer', 'source_balance_id' => $c['source']['id'],
            'destination_balance_id' => $c['destination']['id'], 'quantity' => 5,
            'reason' => 'Store allocation',
        ])->assertCreated()->assertJsonPath('after.source.physical', 5)
            ->assertJsonPath('after.destination.physical', 9)->json();

        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'transfer-1'])
            ->assertOk()->assertJsonPath('balances.0.physical', 5)
            ->assertJsonPath('balances.1.physical', 9)->json();

        $this->assertSame(14, collect($receipt['balances'])->sum('physical'));
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['source']['id'], 'reserved_stock' => 3]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['destination']['id'], 'reserved_stock' => 1]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 2);
    }

    public function test_transfer_rejects_same_account_unknown_destination_and_reserved_units(): void
    {
        $c = $this->context();
        $url = '/admin/api/inventory/store-management/reviews';
        $base = ['kind' => 'transfer', 'source_balance_id' => $c['source']['id'], 'quantity' => 1, 'reason' => 'Allocation'];
        $this->actingAs($c['actor'])->postJson($url, [...$base, 'destination_balance_id' => $c['source']['id']])
            ->assertUnprocessable();
        $this->postJson($url, [...$base, 'destination_balance_id' => 999999])->assertNotFound();
        $this->postJson($url, [...$base, 'destination_balance_id' => $c['destination']['id'], 'quantity' => 8])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_stale_review_and_repeated_confirmation_never_repeat_the_ledger_transfer(): void
    {
        $c = $this->context();
        $url = '/admin/api/inventory/store-management/reviews';
        $payload = ['kind' => 'transfer', 'source_balance_id' => $c['source']['id'],
            'destination_balance_id' => $c['destination']['id'], 'quantity' => 2, 'reason' => 'Allocation'];
        $stale = $this->actingAs($c['actor'])->postJson($url, $payload)->assertCreated()->json();
        DB::table('inventory_balances')->where('id', $c['source']['id'])->increment('version');
        $this->postJson($url.'/'.$stale['id'].'/confirm', ['operation_key' => 'stale-transfer'])
            ->assertConflict()->assertJsonPath('error.code', 'balance_changed');

        $fresh = $this->postJson($url, $payload)->assertCreated()->json();
        $first = $this->postJson($url.'/'.$fresh['id'].'/confirm', ['operation_key' => 'fresh-transfer'])->assertOk()->json();
        $second = $this->postJson($url.'/'.$fresh['id'].'/confirm', ['operation_key' => 'fresh-transfer'])->assertOk()->json();
        $this->assertSame($first, $second);
        $this->assertSame(8, (int) DB::table('inventory_balances')->where('id', $c['source']['id'])->value('physical_stock'));
        $this->assertDatabaseCount('inventory_store_operations', 1);
    }

    public function test_check_status_turns_a_destination_blocked_by_a_replaced_source_into_a_recoverable_failure(): void
    {
        $c = $this->context();
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($c['actor'])->postJson($url, [
            'kind' => 'transfer', 'source_balance_id' => $c['source']['id'],
            'destination_balance_id' => $c['destination']['id'], 'quantity' => 2,
            'reason' => 'Recovery status check',
        ])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'transfer-status-check'])
            ->assertOk()->json();

        $tasks = DB::table('inventory_store_sync_tasks')->where('operation_id', $receipt['operation_id'])->get()->keyBy('stage');
        DB::table('inventory_store_sync_tasks')->where('id', $tasks['source']->id)->update(['status' => 'superseded']);
        DB::table('inventory_store_sync_tasks')->where('id', $tasks['destination']->id)->update(['status' => 'waiting_source']);

        $this->postJson('/admin/api/inventory/store-management/operations/'.$receipt['operation_id'].'/check')
            ->assertOk()
            ->assertJsonPath('external_stages.0.status', 'superseded')
            ->assertJsonPath('external_stages.1.status', 'failed')
            ->assertJsonPath('external_stages.1.provider_receipt.code', 'source_update_replaced');

        $this->assertDatabaseHas('inventory_store_sync_tasks', [
            'id' => $tasks['destination']->id,
            'status' => 'failed',
        ]);
    }
}
