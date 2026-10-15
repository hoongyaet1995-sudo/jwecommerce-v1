<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Customer};
class CustomerAddressRemovalTest extends TestCase
{
 use RefreshDatabase;
 public function test_only_owned_address_can_be_removed_and_default_is_preserved():void {
  $u=User::factory()->create(['is_admin'=>false]);$c=Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Alex']);
  $a=['first_name'=>'Alex','last_name'=>'Tan','line_1'=>'12 Test Street','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY'];
  $first=$c->addresses()->create($a+['is_default'=>true]);$second=$c->addresses()->create($a+['is_default'=>false]);
  $other=Customer::create(['email'=>'other@example.test','first_name'=>'Other']);$foreign=$other->addresses()->create($a);
  $this->actingAs($u,'customer')->delete('/account/addresses/'.$foreign->id)->assertNotFound();$this->assertTrue($foreign->fresh()->exists);
  $this->delete('/account/addresses/'.$first->id)->assertRedirect(route('customer.account').'#addresses');$this->assertNull($first->fresh());$this->assertTrue((bool)$second->fresh()->is_default);
 }
}
