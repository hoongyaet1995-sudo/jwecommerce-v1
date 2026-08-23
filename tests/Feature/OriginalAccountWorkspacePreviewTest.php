<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OriginalAccountWorkspacePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_account_preview_does_not_replace_official_account_route(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertViewIs('storefront.account')
            ->assertViewHas('customer')
            ->assertViewMissing('accountPayload')
            ->assertDontSee('data-original-account-workspace', false);
    }

    public function test_preview_renders_every_original_account_tab(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get(route('customer.account.original-preview'))
            ->assertOk()
            ->assertViewIs('storefront.account-original-preview')
            ->assertSee('Dashboard')
            ->assertSee('Profile Setting')
            ->assertSee('Change Password')
            ->assertSee('My Address')
            ->assertSee('Latest Purchase')
            ->assertSee('Purchase History')
            ->assertSee('Eligible Voucher')
            ->assertSee('data-original-account-workspace', false);
    }

    public function test_original_account_preview_is_protected_by_the_customer_guard(): void
    {
        $this->get(route('customer.account.original-preview'))
            ->assertRedirect(route('customer.login'));
    }

    public function test_original_account_preview_receives_complete_owner_scoped_payload_and_nested_mappings(): void
    {
        [$owner, $ownerOrder] = $this->customerWithOrder('delivered');
        [, $otherOrder] = $this->customerWithOrder('processing');

        $ownerAddress = $owner->addresses()->create($this->addressAttributes('Owner Studio'));
        $ownerItem = $ownerOrder->items()->create($this->itemAttributes('OWNER-ONLY-SKU', 'Owner-only studio item'));
        $ownerOrderAddress = $ownerOrder->addresses()->create($this->orderAddressAttributes('Owner Dispatch'));

        $otherItem = $otherOrder->items()->create($this->itemAttributes('OTHER-CUSTOMER-SKU', 'Other customer item'));
        $otherOrder->addresses()->create($this->orderAddressAttributes('Other Customer Dispatch'));
        $ownerPayment = $ownerOrder->payments()->create([
            'gateway' => 'stripe',
            'status' => 'captured',
            'reference' => 'txn-owner-account-1001',
            'payload' => ['payment_method' => 'Visa', 'last_four' => '4242'],
        ]);
        $otherOrder->payments()->create([
            'gateway' => 'paypal',
            'status' => 'captured',
            'reference' => 'txn-other-account-9009',
            'payload' => ['payment_method' => 'PayPal', 'last_four' => '9009'],
        ]);
        $product = Product::query()->create([
            'handle' => 'owner-review-product',
            'title' => 'Owner Review Product',
            'base_price' => 12000,
            'status' => 'active',
        ]);
        $ownerReview = ProductReview::query()->create($this->reviewAttributes(
            $owner->id,
            $ownerItem->id,
            $product->id,
            'Owner review title',
        ));
        $otherReview = ProductReview::query()->create($this->reviewAttributes(
            $otherOrder->customer_id,
            $otherItem->id,
            $product->id,
            'Other customer review title',
        ));

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account.original-preview'))
            ->assertOk()
            ->assertViewIs('storefront.account-original-preview')
            ->assertViewHas('accountPayload', function (array $payload) use ($owner, $ownerOrder, $ownerAddress, $ownerItem, $ownerOrderAddress, $ownerPayment, $ownerReview, $otherReview): bool {
                self::assertSame(['user', 'orders', 'addresses', 'wishlist', 'dashboard', 'gaps'], array_keys($payload));
                self::assertSame($owner->user_id, data_get($payload, 'user.id'));
                self::assertSame($ownerOrder->number, data_get($payload, 'orders.0.number'));
                self::assertSame($ownerItem->sku, data_get($payload, 'orders.0.items.0.sku'));
                self::assertSame($ownerReview->id, data_get($payload, 'orders.0.items.0.reviews.0.id'));
                self::assertSame($ownerOrderAddress->line_1, data_get($payload, 'orders.0.addresses.0.line1'));
                self::assertSame($ownerAddress->line_1, data_get($payload, 'addresses.0.line1'));
                self::assertSame($ownerPayment->id, data_get($payload, 'orders.0.payments.0.id'));
                self::assertSame('stripe', data_get($payload, 'orders.0.payments.0.gateway'));
                self::assertSame('captured', data_get($payload, 'orders.0.payments.0.gatewayStatus'));
                self::assertSame('txn-owner-account-1001', data_get($payload, 'orders.0.payments.0.transactionId'));
                self::assertSame('Visa', data_get($payload, 'orders.0.payments.0.paymentMethod'));
                self::assertSame('4242', data_get($payload, 'orders.0.payments.0.lastFour'));
                self::assertSame($ownerOrder->number, data_get($payload, 'dashboard.latestOrder.number'));
                self::assertNotContains('OTHER-CUSTOMER-SKU', data_get($payload, 'orders.*.items.*.sku', []));
                self::assertNotContains('Other Customer Dispatch', data_get($payload, 'orders.*.addresses.*.line1', []));
                self::assertNotContains('txn-other-account-9009', data_get($payload, 'orders.*.payments.*.transactionId', []));
                self::assertNotContains($otherReview->id, data_get($payload, 'orders.*.items.*.reviews.*.id', []));

                return true;
            });
    }

    public function test_original_account_preview_contains_only_signed_in_customer_orders(): void
    {
        [$owner, $ownerOrder] = $this->customerWithOrder('delivered');
        [, $otherOrder] = $this->customerWithOrder('processing');

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account.original-preview'))
            ->assertOk()
            ->assertSee($ownerOrder->number)
            ->assertDontSee($otherOrder->number);
    }

    public function test_preview_preserves_source_dom_and_binds_only_existing_operational_endpoints(): void
    {
        [$owner, $order] = $this->customerWithOrder('delivered');
        $product = Product::query()->create([
            'handle' => 'review-ready-studio-item',
            'title' => 'Review-ready studio item',
            'base_price' => 12000,
            'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'REVIEW-READY-SKU',
            'title' => 'Obsidian / Studio Edition',
            'price' => 12000,
            'stock' => 8,
        ]);
        $item = $order->items()->create($this->itemAttributes('REVIEW-READY-SKU', 'Review-ready studio item', $variant->id));
        $order->payments()->create([
            'gateway' => 'stripe',
            'status' => 'captured',
            'reference' => 'pi_account_preview_4242',
            'payload' => ['payment_method' => 'Visa', 'last_four' => '4242'],
        ]);

        $response = $this->actingAs($owner->user, 'customer')
            ->withSession(['cart' => [$variant->id => 2]])
            ->get(route('customer.account.original-preview'));

        $response->assertOk()
            ->assertSee('data-cart-drawer-open', false)
            ->assertSee('id="cart-drawer"', false)
            ->assertSee('data-cart-count="2"', false)
            ->assertSee('mobileExpandedLayer2', false)
            ->assertSee('class="px-3 pb-3 space-y-2 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 pt-2.5"', false)
            ->assertSee('class="p-2 space-y-1 bg-zinc-50 dark:bg-zinc-950"', false)
            ->assertSee('Flagship Acoustic')
            ->assertSee('Review this product')
            ->assertSee('data-review-form', false)
            ->assertSee('data-review-endpoint="'.route('customer.reviews.store').'"', false)
            ->assertSee('data-order-item-id="'.$item->id.'"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"', false)
            ->assertSee('Profile preferences saved successfully!')
            ->assertSee('New address saved to studio address book.')
            ->assertSee('Password successfully changed and updated!')
            ->assertSee('VISA')
            ->assertSee('4242')
            ->assertDontSee(route('customer.reviews.preview'), false)
            ->assertDontSee('Paid via secured checkout')
            ->assertDontSee('data-pending-real-logic', false)
            ->assertDontSee('pending real logic');

        self::assertMatchesRegularExpression(
            '/data-latest-logistics-carrier[^>]*>\s*—\s*</',
            $response->getContent(),
        );
        self::assertMatchesRegularExpression(
            '/data-latest-logistics-estimate[^>]*>\s*—\s*</',
            $response->getContent(),
        );
    }

    private function customerUser(): User
    {
        $user = User::factory()->create(['is_admin' => false]);

        Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Preview',
            'last_name' => 'Customer',
        ]);

        return $user;
    }

    /**
     * @return array{Customer, Order}
     */
    private function customerWithOrder(string $status): array
    {
        $user = $this->customerUser();
        $customer = $user->customer;
        $order = Order::query()->create([
            'number' => 'JW-PREVIEW-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => $status,
            'subtotal' => 12000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 12000,
        ]);

        return [$customer, $order];
    }

    /** @return array<string, mixed> */
    private function itemAttributes(string $sku, string $title, ?int $productVariantId = null): array
    {
        return [
            'product_variant_id' => $productVariantId,
            'sku' => $sku,
            'title' => $title,
            'variant_title' => 'Obsidian / Studio Edition',
            'image_url' => 'images/catalog/aether-1.jpg',
            'unit_price' => 12000,
            'quantity' => 1,
            'line_total' => 12000,
        ];
    }

    /** @return array<string, mixed> */
    private function orderAddressAttributes(string $lineOne): array
    {
        return [
            'type' => 'shipping',
            'first_name' => 'Preview',
            'last_name' => 'Customer',
            'phone' => '+60 12-345 6789',
            'line_1' => $lineOne,
            'line_2' => 'Suite 7',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'Malaysia',
        ];
    }

    /** @return array<string, mixed> */
    private function addressAttributes(string $lineOne): array
    {
        return [
            'label' => 'Primary Studio',
            'type' => 'shipping',
            'is_default' => true,
            ...$this->orderAddressAttributes($lineOne),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewAttributes(int $customerId, int $orderItemId, int $productId, string $title): array
    {
        return [
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'customer_id' => $customerId,
            'attempt_number' => 1,
            'status' => 'pending',
            'active_lock' => 'active',
            'rating' => 5,
            'title' => $title,
            'comment' => 'Source-fidelity review ownership fixture.',
            'order_number_snapshot' => 'JW-REVIEW-OWNER',
            'product_title_snapshot' => 'Owner Review Product',
            'variant_title_snapshot' => 'Obsidian / Studio Edition',
            'submitted_at' => now(),
        ];
    }
}
