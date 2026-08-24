<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminLoginRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_is_redirected_to_the_admin_dashboard_from_the_admin_login_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }

    public function test_admin_login_form_posts_to_the_admin_login_endpoint(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('action="'.route('admin.login.store').'"', false);
    }

    public function test_authenticated_customer_is_redirected_to_the_customer_account_from_the_customer_login_route(): void
    {
        $customerUser = User::factory()->create(['is_admin' => false]);
        Customer::query()->create([
            'user_id' => $customerUser->id,
            'email' => $customerUser->email,
            'first_name' => 'Customer',
        ]);

        $this->actingAs($customerUser, 'customer')
            ->get('/account/login')
            ->assertRedirect('/account');
    }

    public function test_authenticated_admin_can_open_the_header_announcement_spa_deep_link(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/storefront/header-announcement')
            ->assertOk()
            ->assertSee('window.__JW_ADMIN__', false);
    }
}
