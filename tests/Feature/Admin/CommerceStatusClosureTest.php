<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Admin\OrderOperationsService;
use App\Services\Admin\ShipmentDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommerceStatusClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_persists_names_and_counts_actual_orders_without_browser_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->order('processing');
        $this->order('shipped');
        $this->order('delivered');
        $this->actingAs($admin)->patchJson('/admin/api/commerce-statuses/order/processing', ['name' => 'Preparing order', 'description' => 'Confirmed orders'])->assertOk();
        $data = collect($this->getJson('/admin/api/commerce-statuses/order')->assertOk()->json('data'))->keyBy('code');
        $this->assertSame('Preparing order', $data['processing']['name']);
        $this->assertSame(2, $data['processing']['ordersCount']);
        $this->assertSame(1, $data['completed']['ordersCount']);
        $this->patchJson('/admin/api/commerce-statuses/order/processing', ['name' => 'Changed', 'description' => '', 'inventoryAction' => 'restock'])->assertUnprocessable();
        $this->assertDatabaseHas('commerce_status_definitions', ['scope' => 'order', 'code' => 'processing', 'name' => 'Preparing order']);
    }

    public function test_staff_cannot_rewrite_system_status_definitions_or_inject_effects(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->patchJson('/admin/api/commerce-statuses/order/processing', ['name' => 'Changed', 'description' => ''])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]))->patchJson('/admin/api/commerce-statuses/order/made_up', ['name' => 'X', 'description' => ''])->assertNotFound();
    }

    public function test_prepare_rows_exclude_any_existing_active_manual_shipment_and_keep_history(): void
    {
        $ready = $this->order('processing');
        $active = $this->order('processing');
        $pending = $this->order('pending_payment');
        Shipment::query()->create(['order_id' => $active->id, 'source' => 'manual', 'carrier_name' => 'Manual Courier', 'status' => 'shipped', 'tracking_number' => 'EXISTING']);
        $rows = app(ShipmentDataService::class)->collection();
        $queue = $rows->where('source', 'pending');
        $this->assertCount(1, $queue);
        $this->assertSame((string) $ready->id, $queue->first()['orderId']);
        $this->assertCount(1, $rows->where('source', 'manual'));
    }

    public function test_hold_resume_and_idempotence_do_not_fake_payment_or_dispatch(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->order('processing');
        $ops = app(OrderOperationsService::class);
        $ops->updateStatus($order, null, null, $admin->id, 'Review', false, 'on_hold');
        $this->assertSame('on_hold', $order->fresh()->status);
        $ops->updateStatus($order->fresh(), null, null, $admin->id, null, false, 'processing');
        $ops->updateStatus($order->fresh(), null, null, $admin->id, null, false, 'processing');
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(2, $order->statusHistory()->count());
        $this->assertSame(0, $order->shipments()->count());
        Mail::assertNothingSent();
    }

    public function test_explicit_status_cannot_mark_unpaid_order_paid_or_skip_to_shipping(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->order('pending_payment');
        $this->actingAs($admin)->patchJson('/admin/api/orders/'.$order->id.'/status', ['orderStatus' => 'paid', 'notifyCustomer' => false])->assertUnprocessable();
        $this->patchJson('/admin/api/orders/'.$order->id.'/status', ['orderStatus' => 'processing', 'notifyCustomer' => false])->assertUnprocessable();
        $this->patchJson('/admin/api/orders/'.$order->id.'/status', ['orderStatus' => 'shipped'])->assertUnprocessable();
        $this->patchJson('/admin/api/orders/'.$order->id.'/status', ['fulfillmentStatus' => 'processing', 'paymentStatus' => 'pending', 'notifyCustomer' => false])->assertUnprocessable();
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(0, $order->statusHistory()->count());
        Mail::assertNothingSent();
    }

    private function order(string $status): Order
    {
        static $i = 0;
        $i++;
        $order = Order::query()->create(['number' => 'STATUS-'.$i, 'email' => 'status@example.test', 'currency' => 'MYR', 'status' => $status, 'subtotal' => 100, 'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 100]);
        $order->payments()->create(['gateway' => 'simulated_card', 'status' => $status === 'pending_payment' ? 'pending' : 'paid']);

        return $order;
    }
}
