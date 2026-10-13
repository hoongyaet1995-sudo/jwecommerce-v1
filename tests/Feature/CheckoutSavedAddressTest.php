<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSavedAddressTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCountedWebsiteStock;

    public function test_customer_checkout_prefills_the_default_saved_delivery_address(): void
    {
        [$user, $customer] = $this->customer();

        $otherUser = User::factory()->create(['email' => 'other-customer@example.test']);
        $otherCustomer = Customer::query()->create([
            'user_id' => $otherUser->id,
            'email' => $otherUser->email,
            'first_name' => 'Other',
            'last_name' => 'Customer',
        ]);

        CustomerAddress::query()->create([
            'customer_id' => $otherCustomer->id,
            'label' => 'Other Customer Address',
            'is_default' => true,
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'line_1' => 'Private Address',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'MY',
        ]);

        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'is_default' => true,
            'first_name' => 'Tee',
            'last_name' => 'Qiao Jing',
            'phone' => '0143233862',
            'line_1' => 'No 1, Jalan 11/3',
            'line_2' => 'Taman Cahaya Dua',
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postal_code' => '45600',
            'country' => 'MY',
        ]);

        $response = $this->actingAs($user, 'customer')
            ->withSession(['cart' => [$this->variant()->id => 1]])
            ->get('/checkout');

        $response->assertOk()
            ->assertSee('Saved delivery addresses')
            ->assertSee('Select to prefill details')
            ->assertSee('Home')
            ->assertSee('Default')
            ->assertSee('Use a different address / manual entry')
            ->assertSee('firstName', false)
            ->assertSee('No 1, Jalan 11', false)
            ->assertSee('qiaojing96@example.test', false)
            ->assertDontSee('Other Customer Address')
            ->assertDontSee("x-data='{\n                        savedAddresses", false);
    }

    public function test_guest_checkout_omits_the_saved_address_selector(): void
    {
        $response = $this->withSession(['cart' => [$this->variant()->id => 1]])->get('/checkout');

        $response->assertOk()->assertDontSee('Saved delivery addresses');
    }

    public function test_signed_in_customer_without_saved_addresses_omits_the_selector(): void
    {
        [$user] = $this->customer();

        $response = $this->actingAs($user, 'customer')
            ->withSession(['cart' => [$this->variant()->id => 1]])
            ->get('/checkout');

        $response->assertOk()->assertDontSee('Saved delivery addresses');
    }

    public function test_customer_checkout_uses_the_authenticated_email_and_snapshots_edited_address_fields(): void
    {
        [$user, $customer] = $this->customer();
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary',
                'bankName' => 'Maybank',
                'accountHolderName' => 'JW Studio',
                'accountNumber' => '514000004432',
                'isPrimary' => true,
                'isActive' => true,
            ]],
        ]]]);

        $this->actingAs($user, 'customer')
            ->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', [
                'email' => 'someone-else@example.test',
                'first_name' => 'Tee',
                'last_name' => 'Qiao Jing',
                'phone' => '0143233862',
                'line_1' => 'Edited at checkout',
                'line_2' => 'Suite 8',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postal_code' => '45600',
                'country' => 'MY',
                'delivery' => 'standard',
                'payment_method' => 'bank_transfer',
                'bank_account_id' => 'maybank-primary',
            ])
            ->assertRedirect('/order/success');

        $order = Order::query()->with('addresses')->sole();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($user->email, $order->email);
        $this->assertSame('Edited at checkout', $order->addresses->sole()->line_1);
    }

    public function test_checkout_accepts_the_full_malaysia_country_name_saved_by_the_customer_address_form_for_cod(): void
    {
        [$user, $customer] = $this->customer();
        $variant = $this->variant();
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'is_default' => true,
            'first_name' => 'Tee',
            'last_name' => 'Qiao Jing',
            'line_1' => 'No 1, Jalan 11/3',
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postal_code' => '45600',
            'country' => 'MALAYSIA',
        ]);
        StoreSetting::query()->updateOrCreate(['key' => 'cod_config'], ['value' => ['data' => [
            'isEnabled' => true,
            'minOrderAmount' => 0,
            'maxOrderAmount' => 0,
            'allowedRegions' => 'all',
            'surchargeType' => 'fixed',
            'surchargeAmount' => 0,
        ]]]);

        $this->actingAs($user, 'customer')
            ->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', [
                'email' => $user->email,
                'first_name' => 'Tee',
                'last_name' => 'Qiao Jing',
                'phone' => '0143233862',
                'line_1' => 'No 1, Jalan 11/3',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postal_code' => '45600',
                'country' => 'MALAYSIA',
                'delivery' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertRedirect('/order/success');

        $this->assertSame('MY', Order::query()->sole()->addresses()->sole()->country);
    }

    /** @return array{User, Customer} */
    private function customer(): array
    {
        $user = User::factory()->create([
            'email' => 'qiaojing96@example.test',
            'is_admin' => false,
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Tee',
            'last_name' => 'Qiao Jing',
        ]);

        return [$user, $customer];
    }

    private function variant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Studio Headphones',
            'handle' => 'checkout-address-headphones',
            'brand' => 'JW Studio',
            'description' => 'Test',
            'base_price' => 10000,
            'status' => 'active',
        ]);

        return $this->countedWebsiteVariant($product->variants()->create([
            'sku' => 'CHECKOUT-ADDRESS-1',
            'title' => 'Default',
            'price' => 10000,
            'stock' => 10,
            'enabled' => true,
        ]), 10);
    }
}
