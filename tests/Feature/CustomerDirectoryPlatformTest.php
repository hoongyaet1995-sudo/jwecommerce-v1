<?php
namespace Tests\Feature;
use App\Models\{Customer,CustomerPlatformPresence};
use App\Services\Admin\CustomerDirectoryDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CustomerDirectoryPlatformTest extends TestCase {
 use RefreshDatabase;
 public function test_new_storefront_registration_records_platform_without_chat():void {
  $this->post('/account/register',['name'=>'New Buyer','email'=>'new-platform@example.test','password'=>'SecureTesting123!','password_confirmation'=>'SecureTesting123!','terms'=>'1'])->assertRedirect('/account');
  $c=Customer::where('email','new-platform@example.test')->firstOrFail();$this->assertSame(['storefront'],$c->platformPresences()->pluck('platform')->all());
 }
 public function test_platform_and_tag_filters_are_applied_before_pagination():void {
  for($i=0;$i<35;$i++)Customer::create(['email'=>'customer'.$i.'@example.test','first_name'=>'Other']);
  $c=Customer::create(['first_name'=>'WhatsApp Buyer','tags'=>['lead']]);
  CustomerPlatformPresence::create(['customer_id'=>$c->id,'platform'=>'whatsapp','first_seen_at'=>now(),'last_seen_at'=>now()]);
  $data=app(CustomerDirectoryDataService::class)->data(null,'whatsapp','lead');
  $this->assertSame(1,$data['pagination']['total']);$this->assertSame(['whatsapp'],$data['customers'][0]['platforms']);$this->assertNull($data['customers'][0]['email']);
 }
}
