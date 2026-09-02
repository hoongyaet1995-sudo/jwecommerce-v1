<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAddressAmendment;
use App\Models\Shipment;
use App\Models\User;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class OrderDeliveryDetailsApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_address_amendment_preserves_diff_sync_result_and_actor(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $order = Order::query()->create([
            'number' => 'JW-ADDRESS-AMEND-1', 'email' => 'address@example.test', 'currency' => 'MYR',
            'status' => 'processing', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $address = $order->addresses()->create([
            'type' => 'shipping', 'first_name' => 'Tee', 'last_name' => 'Jing',
            'phone' => '0143323862', 'line_1' => 'Old address', 'city' => 'Shah Alam',
            'state' => 'Selangor', 'postal_code' => '45600', 'country' => 'Malaysia',
        ]);

        $amendment = OrderAddressAmendment::query()->create([
            'order_id' => $order->id,
            'order_address_id' => $address->id,
            'before_snapshot' => ['line_1' => 'Old address'],
            'after_snapshot' => ['line_1' => 'New address'],
            'reason_code' => 'customer_request',
            'internal_note' => 'Customer verified new address.',
            'address_book_sync_requested' => false,
            'address_book_sync_result' => 'not_requested',
            'created_by' => $actor->id,
        ]);

        $this->assertFalse($amendment->address_book_sync_requested);
        $this->assertSame('New address', $amendment->after_snapshot['line_1']);
        $this->assertTrue($order->addressAmendments->contains($amendment));
        $this->assertTrue($address->amendments->contains($amendment));
        $this->assertTrue($actor->is($amendment->creator));
    }

    public function test_dispatch_staff_can_amend_current_order_and_active_local_destination_with_audit(): void
    {
        [$order, $address, $shipment, $actor] = $this->deliveryFixtures('JW-ADDRESS-API-1');
        $actor->permissions()->create(['permission' => ShippingPermission::DISPATCH]);

        $response = $this->actingAs($actor)->patchJson('/admin/api/orders/'.$order->id.'/delivery-details', [
            'firstName' => 'Tee', 'lastName' => 'Qiao Jing', 'phone' => '0143323862',
            'address1' => '88 Jalan Baru', 'address2' => 'Unit 8', 'postalCode' => '40150',
            'city' => 'Shah Alam', 'state' => 'Selangor', 'country' => 'Malaysia',
            'reasonCode' => 'customer_request',
            'internalNote' => 'Customer verified the amended destination by phone.',
            'syncAddressBook' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.shippingAddress.address1', '88 Jalan Baru')
            ->assertJsonPath('data.addressBookSyncResult', 'not_requested')
            ->assertJsonPath('data.requiresReplacement', false);
        $this->assertSame('88 Jalan Baru', $address->fresh()->line_1);
        $this->assertSame('88 Jalan Baru', data_get($shipment->fresh()->destination_snapshot, 'line_1'));
        $this->assertDatabaseHas('order_address_amendments', [
            'order_id' => $order->id,
            'order_address_id' => $address->id,
            'reason_code' => 'customer_request',
            'created_by' => $actor->id,
        ]);
    }

    public function test_delivery_amendment_validates_malaysian_contact_and_warns_for_active_provider_booking(): void
    {
        [$order, , $shipment] = $this->deliveryFixtures('JW-ADDRESS-PROVIDER-1');
        $shipment->forceFill(['source' => 'easyparcel', 'fulfillment_method' => 'easyparcel'])->save();
        $payload = [
            'firstName' => 'Tee', 'lastName' => 'Qiao Jing', 'phone' => '+60143323862',
            'address1' => '88 Jalan Baru', 'address2' => null, 'postalCode' => 'ABC',
            'city' => 'Shah Alam', 'state' => 'Selangor', 'country' => 'Malaysia',
            'reasonCode' => 'customer_request', 'internalNote' => 'Customer requested correction.',
            'syncAddressBook' => false,
        ];

        $this->actingAs($this->superAdmin())
            ->patchJson('/admin/api/orders/'.$order->id.'/delivery-details', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'postalCode']);

        $payload['phone'] = '0143323862';
        $payload['postalCode'] = '40150';
        $this->actingAs($this->superAdmin())
            ->patchJson('/admin/api/orders/'.$order->id.'/delivery-details', $payload)
            ->assertOk()
            ->assertJsonPath('data.requiresReplacement', true);
    }

    /** @return array{Order, \App\Models\OrderAddress, Shipment, User} */
    private function deliveryFixtures(string $number): array
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $customer = Customer::query()->create([
            'email' => strtolower($number).'@example.test', 'first_name' => 'Tee',
            'last_name' => 'Jing', 'phone' => '0143323862',
        ]);
        $order = Order::query()->create([
            'number' => $number, 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'processing', 'subtotal' => 10000,
            'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $address = $order->addresses()->create([
            'type' => 'shipping', 'first_name' => 'Tee', 'last_name' => 'Jing',
            'phone' => '0143323862', 'line_1' => 'Old address', 'city' => 'Shah Alam',
            'state' => 'Selangor', 'postal_code' => '45600', 'country' => 'Malaysia',
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id, 'sequence' => 1, 'source' => 'manual',
            'fulfillment_method' => 'manual_delivery', 'carrier_name' => 'Manual / Own Delivery',
            'tracking_number' => 'JW-MANUAL-'.$order->id.'-1', 'status' => 'shipped', 'currency' => 'MYR',
        ]);

        return [$order, $address, $shipment, $actor];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}
