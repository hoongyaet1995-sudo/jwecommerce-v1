<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTrackingVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_preserves_tracking_lookup_dom_without_demo_controls_or_fixture_data(): void
    {
        $this->get('/order-tracking')
            ->assertOk()
            ->assertViewIs('storefront.order-tracking')
            ->assertSee('Track Your Order')
            ->assertSee('ORDER SUPPORT')
            ->assertDontSee('Quick Test Autofill')
            ->assertDontSee('Demo Code:')
            ->assertDontSee('DEFAULT_ORDER_HISTORY');

        $this->withSession(['order_tracking.email' => 'guest@example.test'])
            ->get('/order-tracking?step=verify')
            ->assertOk()
            ->assertSee('data-order-tracking-code-cell', false);
    }

    public function test_tracking_lookup_preserves_the_source_light_and_dark_theme_surfaces(): void
    {
        $this->get('/order-tracking')
            ->assertOk()
            ->assertSee('min-h-screen bg-zinc-50 dark:bg-zinc-950', false)
            ->assertSee('bg-white dark:bg-zinc-900/90 border border-zinc-200/90 dark:border-zinc-800', false)
            ->assertSee('bg-zinc-50 dark:bg-zinc-950/80 border', false)
            ->assertSee('border-zinc-300 dark:border-zinc-800 focus:border-amber-500', false)
            ->assertSee('bg-zinc-50 dark:bg-zinc-950/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-800/60', false);
    }

    public function test_tracking_route_registers_the_shared_storefront_theme_root(): void
    {
        $this->get('/order-tracking')
            ->assertOk()
            ->assertSee('data-atelier-theme-root', false)
            ->assertSee('data-atelier-theme="dark"', false);
    }

    public function test_verified_result_requires_a_verified_session_and_reads_the_real_order_number(): void
    {
        $customer = Customer::query()->create(['email' => 'guest@example.test']);
        $order = Order::query()->create([
            'number' => 'JW-REAL-1001', 'customer_id' => $customer->id, 'email' => $customer->email,
            'currency' => 'MYR', 'status' => 'processing', 'subtotal' => 10000, 'discount_amount' => 0,
            'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 10000,
        ]);
        $attempt = OrderTrackingVerification::query()->create([
            'public_id' => (string) Str::uuid(), 'order_id' => $order->id,
            'email_hash' => hash('sha256', $customer->email), 'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10), 'verified_at' => now(), 'consumed_at' => now(),
        ]);

        $this->get('/order-tracking?step=verified_result')
            ->assertOk()
            ->assertDontSee('JW-REAL-1001');

        $this->withSession(['order_tracking.verified_attempt_id' => $attempt->public_id])
            ->get('/order-tracking?step=verified_result')
            ->assertOk()
            ->assertSee('JW-REAL-1001')
            ->assertSee('Preparing Your Order');
    }
}
