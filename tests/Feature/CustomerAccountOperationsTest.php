<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_address_and_wishlist_persist_to_mysql(): void
    {
        $user = User::factory()->create(['is_admin'=>false]);
        $customer = Customer::query()->create(['user_id'=>$user->id,'email'=>$user->email,'first_name'=>'Alex']);
        $category = Category::query()->create(['name'=>'Audio','slug'=>'audio','status'=>'active']);
        $product = Product::query()->create(['category_id'=>$category->id,'title'=>'Studio Headphones','handle'=>'account-headphones','brand'=>'JW Studio','description'=>'Test','base_price'=>10000,'status'=>'active']);

        $this->actingAs($user, 'customer')->patch('/account/profile', ['name'=>'Alex Buyer','phone'=>'0123456789'])->assertRedirect('/account');
        $this->actingAs($user, 'customer')->post('/account/addresses', ['label'=>'Studio','first_name'=>'Alex','last_name'=>'Buyer','line_1'=>'10 Studio Road','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY','is_default'=>1])->assertRedirect('/account');
        $this->actingAs($user, 'customer')->post('/account/wishlist/'.$product->id)->assertSessionHas('status');
        $this->assertDatabaseHas('customers', ['id'=>$customer->id,'first_name'=>'Alex','last_name'=>'Buyer','phone'=>'0123456789']);
        $this->assertDatabaseHas('customer_addresses', ['customer_id'=>$customer->id,'line_1'=>'10 Studio Road','is_default'=>1]);
        $this->assertDatabaseHas('wishlist_items', ['customer_id'=>$customer->id,'product_id'=>$product->id]);
        $this->actingAs($user, 'customer')->get('/account')->assertOk()->assertSee('data-original-account-workspace', false)->assertSee('10 Studio Road');
        $this->actingAs($user, 'customer')->delete('/account/wishlist/'.$product->id)->assertSessionHas('status');
        $this->assertDatabaseMissing('wishlist_items', ['customer_id'=>$customer->id,'product_id'=>$product->id]);
    }
}
