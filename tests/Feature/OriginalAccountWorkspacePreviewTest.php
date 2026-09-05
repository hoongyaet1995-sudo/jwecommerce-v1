<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

class OriginalAccountWorkspacePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_account_route_renders_the_canonical_account_adapter(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertViewIs('storefront.account')
            ->assertViewHas('accountPayload')
            ->assertSee('data-original-account-workspace', false);
    }

    public function test_original_account_preview_route_is_not_registered(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get('/account/preview/original-account')
            ->assertNotFound();
    }

    public function test_canonical_account_uses_the_shared_storefront_shell_and_theme_root(): void
    {
        $accountView = file_get_contents(resource_path('views/storefront/account.blade.php'));

        self::assertIsString($accountView);
        self::assertStringContainsString('<x-layouts.app :title="\'Member Account | \'.$shopBrandName">', $accountView);
        self::assertStringContainsString('<x-storefront.nav active="account" />', $accountView);
        self::assertStringContainsString('<x-storefront.footer />', $accountView);
        self::assertStringNotContainsString('<footer', $accountView);
        self::assertStringNotContainsString('class="min-h-screen dark bg-zinc-950 text-zinc-100', $accountView);
    }

    public function test_official_account_renders_every_original_account_tab(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertViewIs('storefront.account')
            ->assertSee('Dashboard')
            ->assertSee('Profile Setting')
            ->assertSee('Change Password')
            ->assertSee('My Address')
            ->assertSee('Latest Purchase')
            ->assertSee('Purchase History')
            ->assertSee('Available Coupons')
            ->assertSee('data-original-account-workspace', false);
    }

    public function test_canonical_header_is_rendered_once_on_the_official_account_route(): void
    {
        $user = $this->customerUser();
        $accountView = file_get_contents(resource_path('views/storefront/account.blade.php'));

        self::assertIsString($accountView);
        self::assertStringContainsString('<x-storefront.nav active="account" />', $accountView);
        self::assertStringNotContainsString('<header @mouseleave="activeMenuId = null"', $accountView);

        $response = $this->actingAs($user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertViewIs('storefront.account');

        self::assertSame(1, substr_count($response->getContent(), 'data-original-account-workspace'));
        self::assertSame(1, substr_count($response->getContent(), '© 2026 JW Studio. All rights reserved.'));
    }

    public function test_account_adapter_refreshes_mutations_on_its_current_entrypoint(): void
    {
        $user = $this->customerUser();

        $official = $this->actingAs($user, 'customer')
            ->get(route('customer.account'));
        $official->assertOk()
            ->assertSee('accountRefreshUrl: '.Js::from(route('customer.account')).',', false)
            ->assertSee("window.location.assign(this.accountRefreshUrl + (kind === 'address' ? '#addresses' : '#profile'));", false)
            ->assertSee("window.location.assign(this.accountRefreshUrl + '#addresses');", false);
    }

    public function test_account_served_css_contains_the_source_desktop_layout_utilities(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('build/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $cssPath = public_path('build/'.$manifest['resources/css/app.css']['file']);
        $css = file_get_contents($cssPath);

        self::assertStringContainsString('.lg\\:w-72{', $css);
        self::assertStringContainsString('.lg\\:col-span-4{', $css);
        self::assertStringContainsString('.w-13{', $css);
        self::assertStringContainsString('.h-13{', $css);
    }

    public function test_official_account_receives_complete_owner_scoped_payload_and_nested_mappings(): void
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
            ->get(route('customer.account'))
            ->assertOk()
            ->assertViewIs('storefront.account')
            ->assertViewHas('accountPayload', function (array $payload) use ($owner, $ownerOrder, $ownerAddress, $ownerItem, $ownerOrderAddress, $ownerPayment, $ownerReview, $otherReview): bool {
                self::assertSame(['user', 'orders', 'addresses', 'wishlist', 'dashboard', 'vouchers', 'returnPolicy', 'gaps'], array_keys($payload));
                self::assertTrue(data_get($payload, 'returnPolicy.enabled'));
                self::assertSame(30, data_get($payload, 'returnPolicy.returnWindowDays'));
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

    public function test_official_account_contains_only_signed_in_customer_orders(): void
    {
        [$owner, $ownerOrder] = $this->customerWithOrder('delivered');
        [, $otherOrder] = $this->customerWithOrder('processing');

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account'))
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
        $this->activeHeaderMenu();

        $response = $this->actingAs($owner->user, 'customer')
            ->withSession(['cart' => [$variant->id => 2]])
            ->get(route('customer.account'));

        $response->assertOk()
            ->assertSee('data-cart-drawer-open', false)
            ->assertSee('data-cart-count-badge', false)
            ->assertSee('id="cart-drawer"', false)
            ->assertSee('data-cart-count="2"', false)
            ->assertSee('mobileExpandedLayer2', false)
            ->assertSee('class="px-3 pb-3 space-y-2 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 pt-2.5"', false)
            ->assertSee('class="p-2 space-y-1 bg-zinc-50 dark:bg-zinc-950"', false)
            ->assertSee('Account Header Root')
            ->assertSee('Account Header Column')
            ->assertSee('Account Header Leaf')
            ->assertSee('Review this product')
            ->assertSee('data-review-form', false)
            ->assertSee('data-review-endpoint="'.route('customer.reviews.store').'"', false)
            ->assertSee('data-order-item-id="'.$item->id.'"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"', false)
            ->assertSee('Profile preferences saved successfully!')
            ->assertSee('flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-4 py-2.5 rounded-xl border border-emerald-500/20', false)
            ->assertSee('New address saved to studio address book.')
            ->assertSee('text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 p-3.5 rounded-2xl border border-emerald-500/20 flex items-center gap-2', false)
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

        $response->assertDontSee('LATEST DISPATCH DELIVERED')
            ->assertDontSee('Final Delivery & Signature Verification', false)
            ->assertDontSee('IN TRANSIT (ON SCHEDULE)');
    }

    public function test_preview_client_adapter_reloads_redirected_account_mutations_and_transitions_reviews_only_from_json(): void
    {
        [$owner, $order] = $this->customerWithOrder('delivered');
        $product = Product::query()->create([
            'handle' => 'client-state-review-item',
            'title' => 'Client-state review item',
            'base_price' => 12000,
            'status' => 'active',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'CLIENT-STATE-REVIEW-SKU',
            'title' => 'Obsidian / Studio Edition',
            'price' => 12000,
            'stock' => 8,
        ]);
        $item = $order->items()->create($this->itemAttributes('CLIENT-STATE-REVIEW-SKU', 'Client-state review item', $variant->id));

        $response = $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account'));

        $response->assertOk()
            ->assertSee('reviewStatuses:', false)
            ->assertSee('response.redirected', false)
            ->assertSee("! contentType.includes('application/json')", false)
            ->assertSee("this.reviewStatuses = {...this.reviewStatuses, [targetId]: 'pending_approval'};", false)
            ->assertSee('setTimeout(() => this.closeReview(), 1600);', false)
            ->assertSee("window.location.assign(this.accountRefreshUrl + (kind === 'address' ? '#addresses' : '#profile'));", false)
            ->assertSee("['pending_approval', 'pending'].includes(reviewStatuses[", false)
            ->assertDontSee('form.submit();', false)
            ->assertSee('data-order-item-id="'.$item->id.'"', false);
    }

    public function test_shipped_order_without_concrete_tracking_does_not_claim_a_tracking_state_or_schedule(): void
    {
        [$owner] = $this->customerWithOrder('shipped');

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('LATEST ORDER')
            ->assertSee('>SHIPPED<', false)
            ->assertDontSee('LATEST DISPATCH IN TRANSIT')
            ->assertDontSee('IN TRANSIT (ON SCHEDULE)')
            ->assertDontSee('Handed Over to Courier');
    }

    public function test_shipped_order_with_concrete_tracking_uses_real_carrier_number_and_timestamp_without_schedule_promise(): void
    {
        [$owner, $order] = $this->customerWithOrder('shipped');
        $order->forceFill([
            'tracking_carrier' => 'FedEx International Priority',
            'tracking_number' => 'FX-REAL-2048',
            'shipped_at' => '2026-08-22 14:30:00',
        ])->save();

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('LATEST DISPATCH IN TRANSIT')
            ->assertSee('Handed Over to FedEx International Priority')
            ->assertSee('FX-REAL-2048')
            ->assertSee('Aug 22, 02:30 PM')
            ->assertDontSee('IN TRANSIT (ON SCHEDULE)')
            ->assertDontSee('Final Delivery & Signature Verification', false);
    }

    public function test_pending_payment_is_never_described_as_paid(): void
    {
        [$owner, $order] = $this->customerWithOrder('pending_payment');
        $order->payments()->create([
            'gateway' => 'stripe',
            'status' => 'pending',
            'reference' => 'pi_account_pending_1001',
            'payload' => ['payment_method' => 'Visa', 'last_four' => '1001'],
        ]);

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('Payment: PENDING via VISA')
            ->assertSee('Payment Status: PENDING')
            ->assertDontSee('Paid via VISA')
            ->assertDontSee('Total Paid:');
    }

    public function test_account_money_uses_historical_currency_and_rate_instead_of_the_current_shopper_currency(): void
    {
        [$customer, $order] = $this->customerWithOrder('paid');
        $order->update(['currency' => 'USD', 'currency_exchange_rate' => 0.25]);
        $this->actingAs($customer->user, 'customer')
            ->withSession(['storefront_currency' => 'MYR'])
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee('$30.00 USD')
            ->assertDontSee('$120.00 USD');
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

    private function activeHeaderMenu(): void
    {
        $header = Menu::query()->create(['key' => 'header', 'name' => 'Account Header']);
        $root = MenuItem::query()->create([
            'menu_id' => $header->id,
            'label' => 'Account Header Root',
            'url' => '/shop?account-header=root',
            'position' => 1,
            'is_active' => true,
        ]);
        $column = MenuItem::query()->create([
            'menu_id' => $header->id,
            'parent_id' => $root->id,
            'label' => 'Account Header Column',
            'url' => '/shop?account-header=column',
            'position' => 1,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'menu_id' => $header->id,
            'parent_id' => $column->id,
            'label' => 'Account Header Leaf',
            'url' => '/shop?account-header=leaf',
            'position' => 1,
            'is_active' => true,
        ]);
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
