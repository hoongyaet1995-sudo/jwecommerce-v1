<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Customer};
use Illuminate\Support\Facades\Hash;
class CustomerPasswordUpdateTest extends TestCase
{
 use RefreshDatabase;
 private function customer():User {$u=User::factory()->create(['is_admin'=>false,'password'=>'Original-test-password1']);Customer::create(['user_id'=>$u->id,'email'=>$u->email,'first_name'=>'Alex','last_name'=>'Tan']);return $u;}
 public function test_current_password_and_confirmation_are_required_before_any_update():void {
  $u=$this->customer();$this->actingAs($u,'customer')->patch('/account/password',['current_password'=>'wrong','password'=>'Replacement-password2','password_confirmation'=>'Replacement-password2'])->assertSessionHasErrors('current_password');
  $this->assertTrue(Hash::check('Original-test-password1',$u->fresh()->password));
 }
 public function test_valid_update_changes_hash_and_rotates_remember_token():void {
  $u=$this->customer();$before=$u->remember_token;
  $this->actingAs($u,'customer')->patch('/account/password',['current_password'=>'Original-test-password1','password'=>'Replacement-password2','password_confirmation'=>'Replacement-password2'])->assertRedirect(route('customer.account').'#profile');
  $this->assertTrue(Hash::check('Replacement-password2',$u->fresh()->password));$this->assertNotEquals($before,$u->fresh()->remember_token);
 }
 public function test_guest_cannot_update_password():void {$this->patch('/account/password',[])->assertRedirect();}
}
