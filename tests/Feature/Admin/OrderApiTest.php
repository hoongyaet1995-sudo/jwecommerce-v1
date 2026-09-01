<?php

namespace Tests\Feature\Admin;

use App\Domain\Notifications\NotificationPermission;
use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        app(NotificationTemplateDefaults::class)->synchronize();
    }

    public function test_admin_can_progress_dispatch_and_refund_order_with_audited_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->pendingOrder();

        $this->actingAs($admin)->getJson('/admin/api/orders')->assertOk()
            ->assertJsonPath('orders.0.orderNumber', 'JW-TEST-1001')
            ->assertJsonPath('orders.0.paymentGatewayStatus', 'pending');

        $this->actingAs($admin)->patchJson('/admin/api/orders/'.$order->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'note' => 'Payment confirmed and packing started.',
        ])->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'processing')
            ->assertJsonPath('data.paymentGatewayStatus', 'paid')
            ->assertJsonPath('data.notificationSummary.state', NotificationDispatch::STATE_SENT);

        $this->actingAs($admin)->patchJson('/admin/api/orders/'.$order->id.'/tracking', [
            'carrier' => 'DHL Express', 'trackingNumber' => 'DHL-TRACK-1001',
        ])->assertOk()->assertJsonPath('data.fulfillmentStatus', 'shipped')->assertJsonPath('data.trackingNumber', 'DHL-TRACK-1001');

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'DHL-TRACK-1001',
            'status' => 'shipped',
        ]);

        $this->actingAs($admin)->postJson('/admin/api/orders/'.$order->id.'/refund', [
            'amount' => 50, 'reason' => 'Customer goodwill adjustment.',
        ])->assertOk()->assertJsonPath('data.paymentGatewayStatus', 'partially_refunded');

        $this->assertDatabaseHas('order_refunds', ['order_id' => $order->id, 'amount' => 5000, 'user_id' => $admin->id, 'status' => 'completed']);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'to_status' => 'shipped', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'status' => 'partially_refunded']);

        $this->withSession(['last_order_id' => $order->id])->get('/order/success')
            ->assertOk()->assertSee('DHL-TRACK-1001');
    }

    public function test_refund_cannot_exceed_remaining_order_total(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->pendingOrder();
        $order->update(['status' => 'paid']);
        $order->payments()->update(['status' => 'paid']);

        $this->actingAs($admin)->postJson('/admin/api/orders/'.$order->id.'/refund', ['amount' => 101, 'reason' => 'Invalid over-refund'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_admin_order_payload_exposes_immutable_tax_evidence_and_refund_balances(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->pendingOrder();
        $order->update(['tax_amount' => 600, 'total' => 10600]);
        $line = $order->taxLines()->create([
            'label' => 'Sales Tax 6%',
            'tax_type' => 'standard',
            'rate_percent' => '6.0000',
            'taxable_amount' => 10000,
            'tax_amount' => 600,
            'configuration_snapshot' => [
                'quoted_at' => '2026-08-26T12:00:00+08:00',
                'quote_line' => ['kind' => 'merchandise'],
                'tax_class' => ['id' => 7, 'code' => 'standard-goods', 'name' => 'Standard Goods'],
                'tax_zone' => ['id' => 9, 'code' => 'my', 'name' => 'Malaysia'],
            ],
        ]);

        $this->actingAs($admin)->getJson('/admin/api/orders')->assertOk()
            ->assertJsonPath('orders.0.taxLines.0.id', (string) $line->id)
            ->assertJsonPath('orders.0.taxLines.0.kind', 'merchandise')
            ->assertJsonPath('orders.0.taxLines.0.label', 'Sales Tax 6%')
            ->assertJsonPath('orders.0.taxLines.0.taxClassName', 'Standard Goods')
            ->assertJsonPath('orders.0.taxLines.0.taxZoneName', 'Malaysia')
            ->assertJsonPath('orders.0.taxLines.0.taxableAmount', 100)
            ->assertJsonPath('orders.0.taxLines.0.taxAmount', 6)
            ->assertJsonPath('orders.0.refundedAmount', 0)
            ->assertJsonPath('orders.0.remainingRefundableAmount', 106);
    }

    public function test_order_mutation_requires_admin_authentication(): void
    {
        $order = $this->pendingOrder();
        $this->patchJson('/admin/api/orders/'.$order->id.'/status', ['paymentStatus' => 'paid', 'fulfillmentStatus' => 'unfulfilled'])
            ->assertUnauthorized();
    }

    public function test_order_reads_never_reuse_a_prior_delivery_as_mutation_intent(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->pendingOrder();
        NotificationDispatch::factory()->sent()->create(['order_id' => $order->id]);
        NotificationDispatch::factory()->failed()->create(['order_id' => $order->id]);

        $this->actingAs($admin)->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.notificationSummary', null);

        $this->actingAs($admin)->patchJson('/admin/api/orders/'.$order->id.'/status', [
            'paymentStatus' => 'paid',
            'fulfillmentStatus' => 'processing',
            'notifyCustomer' => false,
        ])->assertOk()
            ->assertJsonPath('data.notificationSummary', null);
    }

    public function test_status_mutation_returns_only_the_new_sent_dispatch_claimed_by_that_operation(): void
    {
        Queue::fake();
        $viewer = $this->staffWith([NotificationPermission::DELIVERIES_VIEW]);
        $order = $this->pendingOrder();
        $prior = NotificationDispatch::factory()->failed()->create(['order_id' => $order->id]);

        $response = $this->actingAs($viewer)->patchJson('/admin/api/orders/'.$order->id.'/status', [
            'paymentStatus' => 'paid',
            'fulfillmentStatus' => 'processing',
            'notifyCustomer' => true,
        ])->assertOk()
            ->assertJsonPath('data.notificationSummary.eventKey', 'payment_verified')
            ->assertJsonPath('data.notificationSummary.state', NotificationDispatch::STATE_SENT)
            ->assertJsonPath('data.notificationSummary.source', NotificationDispatch::SOURCE_AUTOMATIC);

        $this->assertNotSame($prior->public_id, $response->json('data.notificationSummary.publicId'));
    }

    public function test_disabled_deduped_and_noop_status_updates_return_null_intent_even_with_prior_rows(): void
    {
        Queue::fake();
        $viewer = $this->staffWith([NotificationPermission::DELIVERIES_VIEW]);

        $disabledOrder = $this->pendingOrder();
        NotificationDispatch::factory()->sent()->create(['order_id' => $disabledOrder->id]);
        NotificationTemplate::query()->where('event_key', 'payment_verified')->update(['email_enabled' => false]);
        $this->actingAs($viewer)->patchJson('/admin/api/orders/'.$disabledOrder->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'notifyCustomer' => true,
        ])->assertOk()->assertJsonPath('data.notificationSummary', null);

        NotificationTemplate::query()->where('event_key', 'payment_verified')->update(['email_enabled' => true]);
        $dedupedOrder = $this->pendingOrder('DEDUP');
        NotificationDispatch::factory()->sent()->create([
            'order_id' => $dedupedOrder->id,
            'event_key' => 'payment_verified',
            'idempotency_key' => "automatic:email:order:{$dedupedOrder->id}:event:payment_verified",
        ]);
        $this->actingAs($viewer)->patchJson('/admin/api/orders/'.$dedupedOrder->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'notifyCustomer' => true,
        ])->assertOk()->assertJsonPath('data.notificationSummary', null);

        $noopOrder = $this->pendingOrder('NOOP');
        $noopOrder->update(['status' => 'processing']);
        $noopOrder->payments()->update(['status' => 'paid']);
        NotificationDispatch::factory()->failed()->create(['order_id' => $noopOrder->id]);
        $this->actingAs($viewer)->patchJson('/admin/api/orders/'.$noopOrder->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'notifyCustomer' => true,
        ])->assertOk()->assertJsonPath('data.notificationSummary', null);
    }

    public function test_status_mutation_summary_requires_the_exact_delivery_view_permission(): void
    {
        Queue::fake();
        $withoutView = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $first = $this->pendingOrder();

        $this->actingAs($withoutView)->patchJson('/admin/api/orders/'.$first->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'notifyCustomer' => true,
        ])->assertOk()->assertJsonPath('data.notificationSummary', null);
        $this->assertDatabaseHas('notification_dispatches', ['order_id' => $first->id, 'event_key' => 'payment_verified']);

        $caseVariant = $this->staffWith([strtoupper(NotificationPermission::DELIVERIES_VIEW)]);
        $second = $this->pendingOrder('CASE');
        $this->actingAs($caseVariant)->patchJson('/admin/api/orders/'.$second->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'notifyCustomer' => true,
        ])->assertOk()->assertJsonPath('data.notificationSummary', null);
    }

    public function test_order_dto_context_fingerprint_tracks_renderer_preferred_customer_and_latest_shipment_truth(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->pendingOrder('CONTEXT');

        $before = $this->actingAs($admin)->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.customerEmail', 'buyer-context@example.com')
            ->json('orders.0.notificationContextFingerprint');

        Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'Ninja Van Preferred',
            'tracking_number' => 'NVP-CONTEXT-1001',
            'status' => 'shipped',
            'currency' => 'USD',
        ]);

        $afterShipmentResponse = $this->actingAs($admin)->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.customerEmail', 'buyer-context@example.com')
            ->assertJsonPath('orders.0.trackingCarrier', 'Ninja Van Preferred')
            ->assertJsonPath('orders.0.trackingNumber', 'NVP-CONTEXT-1001');
        $afterShipment = $afterShipmentResponse->json('orders.0.notificationContextFingerprint');

        $order->customer()->update(['email' => 'preferred-context@example.com']);
        $afterCustomerResponse = $this->actingAs($admin)->getJson('/admin/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.customerEmail', 'preferred-context@example.com')
            ->assertJsonPath('orders.0.trackingCarrier', 'Ninja Van Preferred')
            ->assertJsonPath('orders.0.trackingNumber', 'NVP-CONTEXT-1001');
        $afterCustomer = $afterCustomerResponse->json('orders.0.notificationContextFingerprint');

        $this->assertIsString($before);
        $this->assertIsString($afterShipment);
        $this->assertIsString($afterCustomer);
        $this->assertNotSame($before, $afterShipment);
        $this->assertNotSame($afterShipment, $afterCustomer);
    }

    private function pendingOrder(string $suffix = '1001'): Order
    {
        $customer = Customer::query()->create(['email' => 'buyer-'.strtolower($suffix).'@example.com', 'first_name' => 'Alex', 'last_name' => 'Buyer']);
        $order = Order::query()->create(['number' => 'JW-TEST-'.$suffix, 'customer_id' => $customer->id, 'email' => $customer->email, 'currency' => 'USD',
            'status' => 'pending_payment', 'subtotal' => 10000, 'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000]);
        $order->items()->create(['sku' => 'SNAPSHOT-1', 'title' => 'Snapshot Product', 'variant_title' => 'Black', 'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        $order->addresses()->create(['type' => 'shipping', 'first_name' => 'Alex', 'last_name' => 'Buyer', 'line_1' => '10 Studio Road', 'city' => 'Kuala Lumpur', 'postal_code' => '50000', 'country' => 'MY']);
        $order->payments()->create(['gateway' => 'simulated_card', 'status' => 'pending', 'reference' => 'SIM-'.$suffix]);
        $order->statusHistory()->create(['to_status' => 'pending_payment']);

        return $order;
    }

    /** @param list<string> $permissions */
    private function staffWith(array $permissions): User
    {
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        foreach ($permissions as $permission) {
            $staff->permissions()->create(['permission' => $permission]);
        }

        return $staff;
    }
}
