<?php

namespace Tests\Feature\Payment;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostedPaymentReturnViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_hosted_return_renders_all_approved_states_without_preview_controls(): void
    {
        [$variant, $payment, $order] = $this->payment();
        $session = ['cart' => [$variant->id => 1], 'pending_payment_id' => $payment->id];

        $this->withSession($session)
            ->get(route('checkout.payment-return', ['payment' => $payment, 'state' => 'redirecting']))
            ->assertOk()
            ->assertSee('Redirecting securely to Stripe…')
            ->assertSee('Do not close or refresh this page.');

        $this->withSession($session)
            ->get(route('checkout.payment-return', ['payment' => $payment, 'state' => 'cancelled']))
            ->assertOk()
            ->assertSee('Payment was cancelled. Your order has not been paid.')
            ->assertSee('Try again');

        $this->withSession($session)
            ->get(route('checkout.payment-return', ['payment' => $payment, 'state' => 'failed']))
            ->assertOk()
            ->assertSee('We could not confirm this payment. No charge was captured.');

        $this->withSession($session)
            ->get(route('checkout.payment-return', ['payment' => $payment, 'state' => 'pending']))
            ->assertOk()
            ->assertSee('Payment submitted — awaiting secure confirmation.')
            ->assertSee($order->number)
            ->assertDontSee('Preview Hosted State');
    }

    private function payment(): array
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Studio Headphones', 'handle' => 'studio-headphones', 'brand' => 'JW Studio', 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => 'STUDIO-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
        $customer = Customer::query()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create(['number' => 'JW-RETURN-123', 'customer_id' => $customer->id, 'email' => $customer->email, 'currency' => 'MYR', 'status' => 'pending_payment', 'subtotal' => 10000, 'total' => 10000]);
        $payment = OrderPayment::query()->create(['order_id' => $order->id, 'gateway' => 'stripe', 'provider' => 'stripe', 'environment' => 'sandbox', 'reference' => 'cs_test_123', 'status' => 'pending', 'amount' => 10000, 'currency' => 'MYR']);

        return [$variant, $payment, $order];
    }
}
