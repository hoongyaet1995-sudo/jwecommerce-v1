<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryReviewTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->permissions()->create(['permission' => 'inventory.edit']);

        return $user;
    }

    /** @return array{actor:User,balance:array<string,mixed>} */
    private function context(?int $physical = null, ?int $reserved = null): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $sku = (new StoreInventoryFixture)->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        if ($physical !== null || $reserved !== null) {
            DB::table('inventory_balances')->where('id', $balance['id'])->update([
                'physical_stock' => $physical,
                'reserved_stock' => $reserved,
                'safety_stock' => 0,
                'counted_at' => $physical === null ? null : now(),
            ]);
            $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        }

        return ['actor' => $this->editor(), 'balance' => $balance];
    }

    public function test_count_is_required_but_zero_is_a_valid_reviewed_count(): void
    {
        $context = $this->context();
        $url = '/admin/api/inventory/store-management/reviews';

        $this->actingAs($context['actor'])->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'reason' => 'Initial shelf count',
        ])->assertUnprocessable()->assertJsonValidationErrors('physical_count');

        $review = $this->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 0,
            'reason' => 'Empty shelf verified',
        ])->assertCreated()->assertJsonPath('after.physical', 0)->json();

        $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'count-zero-1'])
            ->assertOk()->assertJsonPath('balances.0.physical', 0)->assertJsonPath('balances.0.available', 0);
    }

    public function test_review_rejects_a_physical_count_below_existing_reservations(): void
    {
        $context = $this->context(10, 4);

        $this->actingAs($context['actor'])->postJson('/admin/api/inventory/store-management/reviews', [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 3,
            'reason' => 'Shelf count',
        ])->assertUnprocessable()->assertJsonValidationErrors('physical_count');
    }

    public function test_unauthorized_actor_cannot_create_or_confirm_a_review(): void
    {
        $context = $this->context(10, 3);
        $url = '/admin/api/inventory/store-management/reviews';
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson($url, ['kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 12, 'reason' => 'Count'])
            ->assertForbidden();

        $review = $this->actingAs($context['actor'])->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 12, 'reason' => 'Count',
        ])->assertCreated()->json();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'forbidden-confirm'])
            ->assertForbidden();
    }

    public function test_expired_or_changed_review_returns_conflict_without_mutation(): void
    {
        $context = $this->context(10, 3);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($context['actor'])->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 12, 'reason' => 'Count',
        ])->assertCreated()->json();
        DB::table('inventory_store_reviews')->where('id', $review['id'])->update(['expires_at' => now()->subSecond()]);
        $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'expired-review'])
            ->assertConflict()->assertJsonPath('error.code', 'review_expired');
        $this->assertDatabaseHas('inventory_balances', ['id' => $context['balance']['id'], 'physical_stock' => 10]);

        $fresh = $this->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 12, 'reason' => 'Count again',
        ])->assertCreated()->json();
        DB::table('inventory_balances')->where('id', $context['balance']['id'])->increment('version');
        $this->postJson($url.'/'.$fresh['id'].'/confirm', ['operation_key' => 'stale-review'])
            ->assertConflict()->assertJsonPath('error.code', 'balance_changed');
    }

    public function test_repeated_confirmation_returns_saved_receipt_and_operation_key_cannot_change_input(): void
    {
        $context = $this->context(10, 3);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($context['actor'])->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 12, 'reason' => 'Count',
        ])->assertCreated()->json();
        $first = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'same-request'])->assertOk()->json();
        $second = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'same-request'])->assertOk()->json();
        $this->assertSame($first, $second);
        $this->assertSame(12, DB::table('inventory_balances')->where('id', $context['balance']['id'])->value('physical_stock'));
        $this->assertDatabaseCount('inventory_store_operations', 1);

        $another = $this->postJson($url, [
            'kind' => 'count', 'balance_id' => $context['balance']['id'], 'physical_count' => 13, 'reason' => 'Different count',
        ])->assertCreated()->json();
        $this->postJson($url.'/'.$another['id'].'/confirm', ['operation_key' => 'same-request'])
            ->assertConflict()->assertJsonPath('error.code', 'operation_key_reused');
    }

    public function test_intake_and_correction_are_reviewed_deltas(): void
    {
        $context = $this->context(10, 2);
        $url = '/admin/api/inventory/store-management/reviews';
        $intake = $this->actingAs($context['actor'])->postJson($url, [
            'kind' => 'intake', 'balance_id' => $context['balance']['id'], 'quantity' => 5, 'reason' => 'Supplier receipt',
        ])->assertCreated()->assertJsonPath('after.physical', 15)->json();
        $intakeReceipt = $this->postJson($url.'/'.$intake['id'].'/confirm', ['operation_key' => 'intake-1'])->assertOk()->json();
        $this->assertCount(1, $intakeReceipt['external_stages']);

        $correction = $this->postJson($url, [
            'kind' => 'correction', 'balance_id' => $context['balance']['id'], 'quantity_delta' => -2, 'reason' => 'Damaged item',
        ])->assertCreated()->assertJsonPath('after.physical', 13)->json();
        $this->postJson($url.'/'.$correction['id'].'/confirm', ['operation_key' => 'correction-1'])
            ->assertOk()->assertJsonPath('balances.0.physical', 13)->assertJsonPath('balances.0.available', 11);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 2);
    }
}
