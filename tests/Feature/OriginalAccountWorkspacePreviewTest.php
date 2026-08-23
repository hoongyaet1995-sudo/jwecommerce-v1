<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
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

    public function test_original_account_preview_receives_complete_owner_scoped_payload_and_nested_mappings(): void
    {
        [$owner, $ownerOrder] = $this->customerWithOrder('delivered');
        [, $otherOrder] = $this->customerWithOrder('processing');

        $ownerAddress = $owner->addresses()->create($this->addressAttributes('Owner Studio'));
        $ownerItem = $ownerOrder->items()->create($this->itemAttributes('OWNER-ONLY-SKU', 'Owner-only studio item'));
        $ownerOrderAddress = $ownerOrder->addresses()->create($this->orderAddressAttributes('Owner Dispatch'));

        $otherOrder->items()->create($this->itemAttributes('OTHER-CUSTOMER-SKU', 'Other customer item'));
        $otherOrder->addresses()->create($this->orderAddressAttributes('Other Customer Dispatch'));

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.account.original-preview'))
            ->assertOk()
            ->assertViewIs('storefront.account-original-preview')
            ->assertViewHas('accountPayload', function (array $payload) use ($owner, $ownerOrder, $ownerAddress, $ownerItem, $ownerOrderAddress): bool {
                self::assertSame(['user', 'orders', 'addresses', 'wishlist', 'dashboard', 'gaps'], array_keys($payload));
                self::assertSame($owner->user_id, data_get($payload, 'user.id'));
                self::assertSame($ownerOrder->number, data_get($payload, 'orders.0.number'));
                self::assertSame($ownerItem->sku, data_get($payload, 'orders.0.items.0.sku'));
                self::assertSame($ownerOrderAddress->line_1, data_get($payload, 'orders.0.addresses.0.line1'));
                self::assertSame($ownerAddress->line_1, data_get($payload, 'addresses.0.line1'));
                self::assertSame($ownerOrder->number, data_get($payload, 'dashboard.latestOrder.number'));
                self::assertNotContains('OTHER-CUSTOMER-SKU', data_get($payload, 'orders.*.items.*.sku', []));
                self::assertNotContains('Other Customer Dispatch', data_get($payload, 'orders.*.addresses.*.line1', []));

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
    private function itemAttributes(string $sku, string $title): array
    {
        return [
            'product_variant_id' => null,
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
}
