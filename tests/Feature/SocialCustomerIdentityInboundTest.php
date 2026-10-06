<?php
namespace Tests\Feature;
use App\Models\{Customer,SupportConversation};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SocialCustomerIdentityInboundTest extends WhatsAppSupportTest {
 protected function setUp():void {
  parent::setUp();

 }
 public function test_webhook_uses_matching_contact_and_deduplicates_customer():void {
  $p=['object'=>'whatsapp_business_account','entry'=>[['id'=>'789','changes'=>[['field'=>'messages','value'=>['metadata'=>['phone_number_id'=>'456'],'contacts'=>[['wa_id'=>'999','profile'=>['name'=>'Wrong']],['wa_id'=>'60123456789','profile'=>['name'=>'Correct buyer']]],'messages'=>[['from'=>'60123456789','id'=>'unique.crm','timestamp'=>(string)now()->timestamp,'type'=>'text','text'=>['body'=>'Hello']]]]]]]]];
  $s=app(\App\Services\Social\WhatsAppInboundService::class);$this->assertSame(1,$s->receive($p));$this->assertSame(0,$s->receive($p));
  $this->assertSame(1,Customer::count());$this->assertSame('Correct buyer',Customer::first()->first_name);$this->assertSame('+60123456789',Customer::first()->phone);$this->assertNotNull(SupportConversation::first()->customer_id);
 }
}
