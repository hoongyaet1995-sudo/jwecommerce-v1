<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Shipment;
use App\Models\StoreSetting;
use App\Services\Notifications\NotificationContextFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationContextFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_uses_jw_owned_tracking_links_and_excludes_provider_and_proof_data(): void
    {
        StoreSetting::query()->create(['key' => 'support_email', 'value' => ['data' => 'support@jwaistudio.test']]);
        StoreSetting::query()->create(['key' => 'support_phone', 'value' => ['data' => '+60312345678']]);
        StoreSetting::query()->create(['key' => 'whatsapp', 'value' => ['data' => 'https://wa.me/60123456789']]);
        $customer = Customer::query()->create([
            'email' => 'customer@example.test',
            'first_name' => 'Amina',
            'last_name' => 'Buyer',
            'phone' => '+60123456789',
        ]);
        $order = Order::query()->create([
            'number' => 'JW-NOTIFY-1001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 19900,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 19900,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'sku' => 'NOTIFY-SKU-1',
            'title' => 'Operational Product',
            'unit_price' => 19900,
            'quantity' => 1,
            'line_total' => 19900,
        ]);
        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'Amina',
            'last_name' => 'Buyer',
            'phone' => '+60123456789',
            'line_1' => '1 Safe Street',
            'city' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'Malaysia',
        ]);
        OrderPayment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'duitnow',
            'status' => 'paid',
            'reference' => 'PAYMENT-REF-1001',
            'proof_path' => 'payment-proofs/private-receipt.png',
        ]);
        Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Carrier',
            'tracking_number' => 'AWB-1001',
            'status' => 'shipped',
            'currency' => 'MYR',
            'label_path' => 'shipping-labels/private-awb.pdf',
            'provider_reference' => 'EASYPARCEL-TOKEN-1001',
            'tracking_url' => 'https://easyparcel.example.test/tracking/AWB-1001?token=private',
        ]);

        $context = app(NotificationContextFactory::class)->forOrder($order, [
            'staff_note' => 'Packed by warehouse team.',
        ]);

        $this->assertSame(url('/order-tracking'), $context['tracking_url']);
        $this->assertSame(url('/order-tracking'), $context['order_url']);
        $this->assertSame('AWB-1001', $context['tracking_number']);
        $this->assertSame('Packed by warehouse team.', $context['staff_note']);
        $this->assertSame('support@jwaistudio.test', $context['shop_email']);
        $this->assertSame('+60312345678', $context['shop_phone']);
        $this->assertSame('https://wa.me/60123456789', $context['shop_whatsapp']);
        $this->assertArrayNotHasKey('label_path', $context);
        $this->assertArrayNotHasKey('proof_path', $context);
        $this->assertArrayNotHasKey('provider_reference', $context);
        $this->assertStringNotContainsString('easyparcel.example.test', implode('\n', $context));
        $this->assertStringNotContainsString('EASYPARCEL-TOKEN-1001', implode('\n', $context));
        $this->assertStringNotContainsString('private-awb.pdf', implode('\n', $context));
        $this->assertStringNotContainsString('private-receipt.png', implode('\n', $context));
    }

    public function test_context_rejects_extra_values_outside_the_catalog_contract(): void
    {
        $order = Order::query()->create([
            'number' => 'JW-NOTIFY-EXTRA',
            'email' => 'customer@example.test',
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $this->expectException(ValidationException::class);

        app(NotificationContextFactory::class)->forOrder($order, [
            'easyparcel_token' => 'not-for-email',
        ]);
    }
}
