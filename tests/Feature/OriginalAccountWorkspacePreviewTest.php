<?php

namespace Tests\Feature;

use App\Models\Customer;
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
}
