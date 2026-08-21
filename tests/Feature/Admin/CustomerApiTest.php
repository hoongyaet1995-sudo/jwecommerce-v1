<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_customer_profile_with_address_book(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = $this->payload();

        $created = $this->actingAs($admin)->postJson('/admin/api/customers', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'vip')->json('data');

        $this->assertDatabaseHas('customers', ['id' => $created['id'], 'email' => 'buyer@example.com', 'status' => 'vip']);
        $this->assertDatabaseHas('customer_addresses', ['customer_id' => $created['id'], 'line_1' => '10 Studio Road', 'type' => 'shipping']);

        $payload['status'] = 'blocked';
        $payload['notes'] = 'Manual risk review.';
        $payload['addresses'][0]['id'] = (string) $created['addresses'][0]['id'];
        $this->actingAs($admin)->patchJson('/admin/api/customers/'.$created['id'], $payload)
            ->assertOk()->assertJsonPath('data.status', 'blocked')->assertJsonPath('data.notes', 'Manual risk review.');

        $this->actingAs($admin)->getJson('/admin/api/customers')->assertOk()
            ->assertJsonPath('customers.0.email', 'buyer@example.com')->assertJsonPath('customers.0.status', 'blocked');
    }

    public function test_customer_email_must_remain_unique(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->postJson('/admin/api/customers', $this->payload())->assertCreated();
        $duplicate = $this->payload(); $duplicate['name'] = 'Other Buyer';
        $this->actingAs($admin)->postJson('/admin/api/customers', $duplicate)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_write_requires_admin_authentication(): void
    {
        $this->postJson('/admin/api/customers', $this->payload())->assertUnauthorized();
    }

    private function payload(): array
    {
        return ['name'=>'Alex Buyer','email'=>'buyer@example.com','phone'=>'+60123456789','avatar'=>null,
            'status'=>'vip','tags'=>['VIP','wholesale'],'notes'=>'Priority customer.','addresses'=>[[
                'id'=>'new-address','isDefault'=>true,'type'=>'shipping','firstName'=>'Alex','lastName'=>'Buyer',
                'phone'=>'+60123456789','address1'=>'10 Studio Road','address2'=>null,'city'=>'Kuala Lumpur',
                'province'=>'WP Kuala Lumpur','country'=>'MY','postalCode'=>'50000',
            ]]];
    }
}
