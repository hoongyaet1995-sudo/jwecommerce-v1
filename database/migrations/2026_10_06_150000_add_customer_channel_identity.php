<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up():void {
  Schema::table('customers',function(Blueprint $t){$t->string('email')->nullable()->change();if(!Schema::hasColumn('customers','normalized_phone'))$t->string('normalized_phone',16)->nullable()->index();if(!Schema::hasColumn('customers','merged_into_id'))$t->foreignId('merged_into_id')->nullable()->constrained('customers')->nullOnDelete();});
  if(!Schema::hasColumn('support_conversations','customer_id'))Schema::table('support_conversations',fn(Blueprint $t)=>$t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete());
  if(!Schema::hasTable('customer_identity_locks'))Schema::create('customer_identity_locks',function(Blueprint $t){$t->string('key')->primary();});
  if(!Schema::hasTable('customer_channel_identities'))Schema::create('customer_channel_identities',function(Blueprint $t){
   $t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->string('platform',20);$t->string('merchant_key');$t->string('app_id',100);$t->string('account_id',100);$t->string('external_sender_id',100);$t->char('identity_hash',64)->unique();
   $t->string('display_name')->nullable();$t->string('username')->nullable();$t->text('avatar_url')->nullable();$t->json('metadata')->nullable();$t->timestamp('profile_checked_at')->nullable();$t->dateTime('first_seen_at');$t->dateTime('last_seen_at');$t->timestamps();
  });
  if(!Schema::hasTable('customer_platform_presences'))Schema::create('customer_platform_presences',function(Blueprint $t){$t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->string('platform',20);$t->dateTime('first_seen_at');$t->dateTime('last_seen_at');$t->unique(['customer_id','platform']);});
  DB::table('customers')->select('id','phone')->orderBy('id')->chunkById(200,function($rows){foreach($rows as $r)DB::table('customers')->where('id',$r->id)->update(['normalized_phone'=>\App\Services\Customers\ContactValueNormalizer::phone($r->phone)]);});
 }
 public function down():void {
  // Email intentionally stays nullable: rollback must not destroy provisional contacts.
  Schema::dropIfExists('customer_platform_presences');Schema::dropIfExists('customer_channel_identities');Schema::dropIfExists('customer_identity_locks');
  Schema::table('support_conversations',fn(Blueprint $t)=>$t->dropConstrainedForeignId('customer_id'));
  Schema::table('customers',function(Blueprint $t){$t->dropConstrainedForeignId('merged_into_id');$t->dropColumn('normalized_phone');});
 }
};
