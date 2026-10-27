<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
class StaffMarketingPermissionTest extends TestCase {
 use RefreshDatabase;
 public function test_owner_manages_only_marketing_grants_and_preserves_other_permissions():void{
  $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$staff->permissions()->create(['permission'=>'reviews.view']);$this->actingAs($owner)->getJson('/admin/api/staff/marketing-permissions')->assertOk()->assertJsonCount(2,'data');
  $this->putJson('/admin/api/staff/'.$staff->id.'/marketing-permissions',['permissions'=>['marketing.view','marketing.edit']])->assertOk();$this->assertDatabaseHas('user_permissions',['user_id'=>$staff->id,'permission'=>'reviews.view']);$this->assertDatabaseHas('user_permissions',['user_id'=>$staff->id,'permission'=>'marketing.edit']);
  $this->putJson('/admin/api/staff/'.$staff->id.'/marketing-permissions',['permissions'=>[]])->assertOk();$this->assertDatabaseMissing('user_permissions',['user_id'=>$staff->id,'permission'=>'marketing.view']);$this->assertDatabaseHas('user_permissions',['user_id'=>$staff->id,'permission'=>'reviews.view']);
 }
 public function test_staff_cannot_grant_access_and_invalid_combinations_are_rejected():void{
  $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$this->actingAs($staff)->getJson('/admin/api/staff/marketing-permissions')->assertForbidden();$this->putJson('/admin/api/staff/'.$staff->id.'/marketing-permissions',['permissions'=>['marketing.view']])->assertForbidden();
  $this->actingAs($owner)->putJson('/admin/api/staff/'.$staff->id.'/marketing-permissions',['permissions'=>['marketing.approve_cost']])->assertUnprocessable();$this->putJson('/admin/api/staff/'.$owner->id.'/marketing-permissions',['permissions'=>[]])->assertUnprocessable();$this->putJson('/admin/api/staff/'.$staff->id.'/marketing-permissions',['permissions'=>['reviews.view']])->assertUnprocessable();
 }
 public function test_bootstrap_exposes_only_effective_marketing_entry():void{
  $staff=User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]);$this->actingAs($staff)->get('/admin/settings/users')->assertOk()->assertViewHas('bootstrap',fn($b)=>$b['marketingStudioUrl']===null&&$b['marketingPermissions']===[]);$staff->permissions()->create(['permission'=>'marketing.view']);$this->get('/admin/settings/users')->assertOk()->assertViewHas('bootstrap',fn($b)=>$b['marketingStudioUrl']==='/admin/marketing'&&$b['marketingPermissions']===['marketing.view']);
 }
}
