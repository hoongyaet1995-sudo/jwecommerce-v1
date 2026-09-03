<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestOperationalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_one_owned_eligible_item_and_refresh_persisted_rma(): void
    {
        Storage::fake('local');
        [$user, $order, $item] = $this->deliveredOrder();

        $this->actingAs($user, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'resolution' => 'refund',
            'reason' => 'defective',
            'details' => 'Right channel produces audible distortion.',
            'evidence' => [UploadedFile::fake()->create('fault.jpg', 10, 'image/jpeg')],
        ])->assertRedirect('/account#purchase_history');

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'customer_id' => $order->customer_id, 'status' => 'requested',
            'resolution' => 'refund', 'quantity' => 1,
        ]);
        $this->assertDatabaseCount('return_request_evidence', 1);
        $this->assertDatabaseHas('return_request_events', ['event_type' => 'requested', 'actor_type' => 'customer']);

        $this->actingAs($user, 'customer')->get('/account#purchase_history')
            ->assertOk()->assertSee('Waiting for Staff Review')->assertSee('RMA-');
    }

    public function test_customer_cannot_request_an_undelivered_foreign_expired_or_excess_item(): void
    {
        [$user, $order, $item] = $this->deliveredOrder();
        $other = User::factory()->create();
        $otherCustomer = Customer::query()->create(['user_id' => $other->id, 'email' => $other->email]);

        $this->actingAs($other, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id, 'quantity' => 1, 'resolution' => 'refund', 'reason' => 'defective',
        ])->assertNotFound();

        $order->statusHistory()->where('to_status', 'delivered')->update(['created_at' => now()->subDays(31)]);
        $this->actingAs($user, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id, 'quantity' => 1, 'resolution' => 'refund', 'reason' => 'defective',
        ])->assertSessionHasErrors('order_item_id');

        $order->statusHistory()->where('to_status', 'delivered')->update(['created_at' => now()->subDays(2)]);
        $this->actingAs($user, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id, 'quantity' => 2, 'resolution' => 'refund', 'reason' => 'defective',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame($otherCustomer->id, $other->customer->id);
    }

    public function test_admin_lifecycle_persists_refund_and_same_sku_replacement_results(): void
    {
        [$customerUser, $order, $item] = $this->deliveredOrder();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($customerUser, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id, 'quantity' => 1, 'resolution' => 'refund', 'reason' => 'defective',
        ]);
        $requestId = (int) \DB::table('return_requests')->value('id');

        $this->actingAs($admin)->getJson('/admin/api/orders')
            ->assertOk()->assertJsonPath('orders.0.returnRequest.status', 'requested');
        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/approve")
            ->assertOk()->assertJsonPath('data.returnRequest.status', 'approved');
        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/receive", [
            'received_quantity' => 1, 'condition' => 'restockable', 'warehouse_note' => 'Inspected at intake.',
        ])->assertOk()->assertJsonPath('data.returnRequest.status', 'received');
        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/resolve-refund")
            ->assertOk()->assertJsonPath('data.returnRequest.status', 'resolved_refund');
        $this->assertDatabaseHas('order_refunds', ['order_id' => $order->id, 'amount' => $item->unit_price, 'status' => 'completed']);

        [$secondUser, $secondOrder, $secondItem] = $this->deliveredOrder('REPLACE');
        $this->actingAs($secondUser, 'customer')->post('/account/returns', [
            'order_item_id' => $secondItem->id, 'quantity' => 1, 'resolution' => 'replacement', 'reason' => 'damaged_in_transit',
        ]);
        $secondRequestId = (int) \DB::table('return_requests')->where('order_id', $secondOrder->id)->value('id');
        $this->actingAs($admin)->postJson("/admin/api/returns/{$secondRequestId}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/admin/api/returns/{$secondRequestId}/receive", [
            'received_quantity' => 1, 'condition' => 'damaged', 'warehouse_note' => 'Housing damaged.',
        ])->assertOk();
        $response = $this->actingAs($admin)->postJson("/admin/api/returns/{$secondRequestId}/resolve-replacement")
            ->assertOk()->assertJsonPath('data.returnRequest.status', 'resolved_replacement');
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.returnRequest.replacementOrderId'), 'customer_id' => $secondOrder->customer_id, 'status' => 'processing']);
        $this->assertDatabaseHas('order_items', ['order_id' => $response->json('data.returnRequest.replacementOrderId'), 'sku' => $secondItem->sku, 'quantity' => 1]);
    }

    public function test_admin_can_receive_a_restockable_return_without_a_warehouse_note(): void
    {
        [$customerUser, $order, $item] = $this->deliveredOrder('RESTOCKABLE-NO-NOTE');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($customerUser, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'resolution' => 'refund',
            'reason' => 'defective',
        ]);
        $requestId = (int) \DB::table('return_requests')->where('order_id', $order->id)->value('id');

        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/receive", [
            'received_quantity' => 1,
            'condition' => 'restockable',
            'warehouse_note' => '',
        ])->assertOk()->assertJsonPath('data.returnRequest.status', 'received');

        $this->assertDatabaseHas('return_requests', [
            'id' => $requestId,
            'status' => 'received',
            'condition' => 'restockable',
            'warehouse_note' => null,
        ]);
    }

    public function test_admin_must_explain_why_a_received_return_is_damaged(): void
    {
        [$customerUser, $order, $item] = $this->deliveredOrder('DAMAGED-NOTE-REQUIRED');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($customerUser, 'customer')->post('/account/returns', [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'resolution' => 'replacement',
            'reason' => 'damaged_in_transit',
        ]);
        $requestId = (int) \DB::table('return_requests')->where('order_id', $order->id)->value('id');

        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/admin/api/returns/{$requestId}/receive", [
            'received_quantity' => 1,
            'condition' => 'damaged',
            'warehouse_note' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_note');
    }

    private function deliveredOrder(string $suffix = 'REFUND'): array
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $user = User::factory()->create();
        $customer = Customer::query()->create(['user_id' => $user->id, 'email' => $user->email, 'first_name' => 'RMA', 'last_name' => 'Customer']);
        $order = Order::query()->create([
            'number' => 'JW-RMA-'.$suffix, 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'delivered', 'subtotal' => 20000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 20000,
        ]);
        $item = $order->items()->create(['sku' => 'SKU-'.$suffix, 'title' => 'Studio Product', 'variant_title' => 'Obsidian', 'unit_price' => 20000, 'quantity' => 1, 'line_total' => 20000]);
        $order->payments()->create(['gateway' => 'simulated_card', 'status' => 'paid', 'reference' => 'PAY-'.$suffix]);
        $order->statusHistory()->create(['to_status' => 'delivered', 'created_at' => now()->subDays(2)]);

        return [$user, $order, $item];
    }
}
