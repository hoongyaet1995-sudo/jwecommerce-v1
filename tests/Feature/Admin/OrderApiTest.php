<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_progress_dispatch_and_refund_order_with_audited_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $order = $this->pendingOrder();

        $this->actingAs($admin)->getJson('/admin/api/orders')->assertOk()
            ->assertJsonPath('orders.0.orderNumber', 'JW-TEST-1001')
            ->assertJsonPath('orders.0.paymentGatewayStatus', 'pending');

        $this->actingAs($admin)->patchJson('/admin/api/orders/'.$order->id.'/status', [
            'paymentStatus' => 'paid', 'fulfillmentStatus' => 'processing', 'note' => 'Payment confirmed and packing started.',
        ])->assertOk()->assertJsonPath('data.fulfillmentStatus', 'processing')->assertJsonPath('data.paymentGatewayStatus', 'paid');

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
        $this->patchJson('/admin/api/orders/'.$order->id.'/status', ['paymentStatus'=>'paid','fulfillmentStatus'=>'unfulfilled'])
            ->assertUnauthorized();
    }

    private function pendingOrder(): Order
    {
        $customer = Customer::query()->create(['email'=>'buyer@example.com','first_name'=>'Alex','last_name'=>'Buyer']);
        $order = Order::query()->create(['number'=>'JW-TEST-1001','customer_id'=>$customer->id,'email'=>$customer->email,'currency'=>'USD',
            'status'=>'pending_payment','subtotal'=>10000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'total'=>10000]);
        $order->items()->create(['sku'=>'SNAPSHOT-1','title'=>'Snapshot Product','variant_title'=>'Black','unit_price'=>10000,'quantity'=>1,'line_total'=>10000]);
        $order->addresses()->create(['type'=>'shipping','first_name'=>'Alex','last_name'=>'Buyer','line_1'=>'10 Studio Road','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY']);
        $order->payments()->create(['gateway'=>'simulated_card','status'=>'pending','reference'=>'SIM-1001']);
        $order->statusHistory()->create(['to_status'=>'pending_payment']);
        return $order;
    }
}
