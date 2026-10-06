<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Services\Customers\CustomerContactResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerContactResolverTest extends TestCase
{
 protected function setUp(): void {
  parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']); DB::purge('sqlite');
  Schema::create('users',fn(Blueprint $t)=>$t->id());
  Schema::create('customers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('email')->nullable()->unique();$t->string('phone')->nullable();$t->string('first_name')->nullable();$t->string('last_name')->nullable();$t->string('avatar')->nullable();$t->string('status')->nullable();$t->json('tags')->nullable();$t->text('notes')->nullable();$t->timestamps();});
  (require database_path('migrations/2026_10_02_160000_create_support_chat_tables.php'))->up();
  if(file_exists(database_path('migrations/2026_10_06_150000_add_customer_channel_identity.php')))(require database_path('migrations/2026_10_06_150000_add_customer_channel_identity.php'))->up();
 }
 private function identity(string $account='page'):array {return ['platform'=>'facebook','merchant_key'=>'shop','app_id'=>'app','account_id'=>$account,'sender_id'=>'sender'];}
 private function conversation():SupportConversation {return SupportConversation::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'owner_hash'=>hash('sha256',uniqid()),'status'=>'open']);}
 public function test_provisional_contact_is_idempotent_and_scoped():void {
  $s=app(CustomerContactResolver::class);$c=$this->conversation();$a=$s->associate($c,$this->identity());
  $this->assertNull($a->email);$this->assertSame($a->id,$s->associate($c,$this->identity())->id);
  $this->assertNotSame($a->id,$s->associate($this->conversation(),$this->identity('other'))->id);
  $this->assertSame(2,Customer::count());
 }
 public function test_trusted_phone_matches_without_reassigning_user():void {
  $existing=Customer::create(['email'=>'buyer@example.test','phone'=>'+60 12-345 6789','first_name'=>'Buyer']);
  $a=app(CustomerContactResolver::class)->associate($this->conversation(),$this->identity(),['phone'=>'+60123456789','phone_trusted'=>true]);
  $this->assertSame($existing->id,$a->id);$this->assertNull($a->user_id);
 }
 public function test_conflicting_identifiers_and_untrusted_email_do_not_merge():void {
  $a=Customer::create(['email'=>'a@example.test','phone'=>'+60123456789']);$b=Customer::create(['email'=>'b@example.test','phone'=>'+60198765432']);
  $s=app(CustomerContactResolver::class);
  $c=$s->associate($this->conversation(),$this->identity(),['email'=>' A@EXAMPLE.TEST ','email_trusted'=>true,'phone'=>'+60198765432','phone_trusted'=>true]);
  $this->assertNotContains($c->id,[$a->id,$b->id]);$this->assertNull($c->email);
  $u=$s->associate($this->conversation(),$this->identity('other'),['email'=>'a@example.test','name'=>'A']);
  $this->assertNotSame($a->id,$u->id);$this->assertNull($u->email);
 }
 public function test_ambiguous_local_phone_does_not_match():void {
  $a=Customer::create(['phone'=>'0123456789']);
  $c=app(CustomerContactResolver::class)->associate($this->conversation(),$this->identity(),['phone'=>'0123456789','phone_trusted'=>true]);
  $this->assertNotSame($a->id,$c->id);
 }
 public function test_bare_local_phone_is_rejected_even_without_leading_zero():void {
  $this->assertNull(\App\Services\Customers\ContactValueNormalizer::phone('12345678'));
 }
 public function test_later_trusted_enrichment_links_only_provisional_identity():void {
  $buyer=Customer::create(['email'=>'verified@example.test','first_name'=>'Verified']);
  $s=app(CustomerContactResolver::class);$c=$this->conversation();$initial=$s->associate($c,$this->identity());
  $linked=$s->associate($c,$this->identity(),['email'=>'verified@example.test','email_trusted'=>true]);
  $this->assertSame($buyer->id,$linked->id);$this->assertTrue(Customer::whereKey($initial->id)->exists());
 }
 public function test_contradictory_target_fields_block_initial_and_later_association():void {
  $buyer=Customer::create(['email'=>'owner@example.test','phone'=>'+60123456789']);$s=app(CustomerContactResolver::class);$c=$this->conversation();$initial=$s->associate($c,$this->identity());
  $claims=['email'=>'different@example.test','email_trusted'=>true,'phone'=>'+60123456789','phone_trusted'=>true];
  $this->assertSame($initial->id,$s->associate($c,$this->identity(),$claims)->id);
  $this->assertSame($initial->id,$c->fresh()->customer_id);
  $new=$s->associate($this->conversation(),$this->identity('other'),$claims);$this->assertNotSame($buyer->id,$new->id);$this->assertNull($new->email);
 }
}
