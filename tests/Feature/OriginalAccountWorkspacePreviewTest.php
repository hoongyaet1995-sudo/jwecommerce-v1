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

    public function test_official_account_route_is_not_replaced_by_preview(): void
    {
        $this->actingAs($this->customerUser(), 'customer')
            ->get(route('customer.account'))
            ->assertOk()
            ->assertDontSee('data-original-account-workspace', false);
    }

    public function test_preview_contains_only_signed_in_customer_orders(): void
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
}
