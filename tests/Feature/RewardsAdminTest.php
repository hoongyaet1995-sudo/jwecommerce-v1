<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
class RewardsAdminTest extends TestCase {
use RefreshDatabase;
public function test_settings_and_drafts_are_saved_without_activation():void {
$this->getJson('/admin/api/rewards/settings')->assertUnauthorized();
$this->actingAs(User::factory()->create(['is_admin'=>true]));
$d=['coins_per_unit'=>100,'max_discount_percent'=>20,'minimum_coins'=>100,'expiry_months'=>12,'enabled'=>false];
$this->putJson('/admin/api/rewards/settings',$d)->assertOk();
$this->getJson('/admin/api/rewards/settings')->assertJsonPath('data.coins_per_unit',100);
$this->putJson('/admin/api/rewards/settings',array_replace($d,['enabled'=>true]))->assertOk()->assertJsonPath('data.enabled',true);
$o=['name'=>'Test cashback','description'=>'','scope'=>'all','product_ids'=>[],'rate'=>5,'minimum_spend'=>0,'maximum_reward'=>2000,'starts_at'=>null,'ends_at'=>null,'enabled'=>false];
$r=$this->postJson('/admin/api/rewards/offers',$o)->assertCreated();
$this->getJson('/admin/api/rewards/offers')->assertJsonCount(1,'data');
$this->patchJson('/admin/api/rewards/offers/'.$r->json('data.id'),array_replace($o,['rate'=>101]))->assertUnprocessable();
$this->postJson('/admin/api/rewards/offers',array_replace($o,['enabled'=>true]))->assertCreated()->assertJsonPath('data.enabled',true);
}
}
