<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use App\Domain\Accounts\OriginalAccountPayloadFactory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCouponClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_apply_a_current_coupon_to_their_cart_session_without_creating_usage(): void
    {
        [$customer, $variant] = $this->customerWithVariant();
        Coupon::query()->create([
            'code' => 'ACCOUNT10',
            'name' => 'Account ten',
            'discount_type' => 'percentage',
            'value' => 10,
            'min_spend' => 10000,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($customer->user, 'customer')
            ->withSession(['cart' => [$variant->id => 1]])
            ->post(route('customer.coupons.apply'), ['code' => 'account10'])
            ->assertRedirect(route('customer.account').'#vouchers')
            ->assertSessionHas('coupon_code', 'ACCOUNT10')
            ->assertSessionHas('coupon_status.type', 'success');

        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseHas('coupons', ['code' => 'ACCOUNT10', 'usage_count' => 0]);
    }

    public function test_personally_exhausted_coupon_cannot_replace_the_existing_cart_coupon(): void
    {
        [$customer, $variant] = $this->customerWithVariant();
        $coupon = Coupon::query()->create([
            'code' => 'ONCEONLY',
            'name' => 'Once only',
            'discount_type' => 'percentage',
            'value' => 10,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'JW-USED-ONCE',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'subtotal' => 10000,
            'total' => 9000,
        ]);
        CouponUsage::query()->create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'discount_amount' => 1000,
        ]);

        $this->actingAs($customer->user, 'customer')
            ->withSession(['cart' => [$variant->id => 1], 'coupon_code' => 'KEEP10'])
            ->post(route('customer.coupons.apply'), ['code' => 'ONCEONLY'])
            ->assertRedirect(route('customer.account').'#vouchers')
            ->assertSessionHas('coupon_code', 'KEEP10')
            ->assertSessionHas('coupon_status.type', 'error');
    }

    public function test_cart_coupon_entry_uses_the_same_customer_usage_limit_as_the_account(): void
    {
        [$customer, $variant] = $this->customerWithVariant();
        $coupon = Coupon::query()->create([
            'code' => 'CARTONCE', 'name' => 'Cart once', 'discount_type' => 'percentage', 'value' => 10,
            'per_customer_limit' => 1, 'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'JW-CART-USED', 'customer_id' => $customer->id, 'email' => $customer->email,
            'subtotal' => 10000, 'total' => 9000,
        ]);
        CouponUsage::query()->create([
            'coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_id' => $customer->id,
            'customer_email' => $customer->email, 'discount_amount' => 1000,
        ]);

        $this->actingAs($customer->user, 'customer')
            ->withSession(['cart' => [$variant->id => 1]])
            ->post(route('cart.coupon.apply'), ['code' => 'CARTONCE'])
            ->assertRedirect(route('cart.show'))
            ->assertSessionMissing('coupon_code')
            ->assertSessionHas('coupon_error', 'This coupon is no longer available for this customer.');
    }

    public function test_account_payload_exposes_real_available_coupon_cards_and_removes_vouchers_gap(): void
    {
        [$customer] = $this->customerWithVariant();
        Coupon::query()->create(['code' => 'REAL20', 'name' => 'Real twenty', 'discount_type' => 'percentage', 'value' => 20, 'per_customer_limit' => 2, 'is_active' => true]);

        $payload = app(OriginalAccountPayloadFactory::class)->for($customer->fresh());

        self::assertSame('REAL20', data_get($payload, 'vouchers.eligible.0.code'));
        self::assertSame(1, data_get($payload, 'vouchers.counts.eligible'));
        self::assertNotContains('vouchers', $payload['gaps']);
    }

    public function test_account_renders_truthful_coupon_counts_and_a_real_coupon_submission_form(): void
    {
        [$customer] = $this->customerWithVariant();
        Coupon::query()->create([
            'code' => 'REAL20',
            'name' => 'Real twenty',
            'discount_type' => 'percentage',
            'value' => 20,
            'min_spend' => 10000,
            'per_customer_limit' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($customer->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('Eligible & Active (1)', false)
            ->assertSee('>1</div><span class="text-[11px] text-zinc-500', false)
            ->assertSee('action="'.route('customer.coupons.apply').'"', false)
            ->assertSee('Min. spend RM 100.00')
            ->assertDontSee('VIP20, STUDIO15, WELCOME10, FREESHIP, ATELIER50')
            ->assertDontSee('claimed and added to your wallet');
    }

    public function test_admin_coupon_api_rules_are_the_same_rules_rendered_in_the_customer_account(): void
    {
        [$customer] = $this->customerWithVariant();
        $admin = User::factory()->create(['is_admin' => true]);

        $created = $this->actingAs($admin)
            ->postJson(route('admin.api.coupons.index'), [
                'code' => 'ADMIN20', 'name' => 'Admin twenty', 'description' => 'Created in the admin promotion editor.',
                'discountType' => 'fixed_amount', 'value' => 20, 'minimumSpend' => 100,
                'perCustomerLimit' => 1, 'isActive' => true,
            ])->assertCreated()->json('data');

        $this->actingAs($customer->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('ADMIN20')
            ->assertSee('RM 20.00')
            ->assertSee('Eligible & Active (1)', false);

        $this->actingAs($admin)
            ->patchJson('/admin/api/coupons/'.$created['id'], [
                'code' => 'ADMIN20', 'name' => 'Admin twenty', 'description' => 'Created in the admin promotion editor.',
                'discountType' => 'fixed_amount', 'value' => 20, 'minimumSpend' => 100,
                'perCustomerLimit' => 1, 'isActive' => false,
            ])->assertOk();

        $this->actingAs($customer->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('Eligible & Active (0)', false)
            ->assertSee('Unavailable (1)', false)
            ->assertSee('ADMIN20');
    }

    /** @return array{Customer, \App\Models\ProductVariant} */
    private function customerWithVariant(): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Coupon',
            'last_name' => 'Customer',
        ]);
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Coupon Test Product',
            'handle' => 'coupon-test-product',
            'brand' => 'JW Studio',
            'description' => 'Test',
            'base_price' => 10000,
            'status' => 'active',
        ]);

        return [$customer, $product->variants()->create([
            'sku' => 'COUPON-TEST-1',
            'title' => 'Default',
            'price' => 10000,
            'stock' => 10,
            'enabled' => true,
        ])];
    }
}
