<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('marketing_tracking_links',function(Blueprint $t){$t->uuid('post_id')->nullable()->change();$t->string('channel',20)->default('post');});
  Schema::create('marketing_email_deliveries',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('campaign_id');$t->unsignedBigInteger('user_id');$t->string('merchant_key',100);$t->unsignedInteger('campaign_version');$t->text('recipient');$t->string('recipient_hash',64);$t->string('state',30);$t->uuid('link_id');$t->json('snapshot');$t->string('error_code',80)->nullable();$t->timestamp('accepted_at')->nullable();$t->timestamps();$t->unique(['campaign_id','campaign_version','recipient_hash'],'marketing_email_once');});
 }
 public function down():void {Schema::dropIfExists('marketing_email_deliveries');Schema::table('marketing_tracking_links',fn(Blueprint $t)=>$t->dropColumn('channel'));}
};
