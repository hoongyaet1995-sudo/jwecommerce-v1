<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketing_email_batches',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('campaign_id');$t->string('merchant_key',100);$t->unsignedBigInteger('user_id');$t->unsignedInteger('campaign_version');$t->string('digest',64);$t->string('state',24);$t->unsignedInteger('total');$t->unsignedInteger('excluded');$t->unsignedInteger('cursor')->default(0);$t->longText('payload');$t->string('error_code',80)->nullable();$t->timestamp('confirmed_at')->nullable();$t->dateTime('expires_at');$t->timestamps();$t->unique(['campaign_id','campaign_version'],'marketing_email_batch_once');});Schema::create('marketing_email_feedback',function(Blueprint $t){$t->bigIncrements('id');$t->string('merchant_key',100);$t->uuid('delivery_id');$t->string('event_hash',64)->unique();$t->string('kind',24);$t->dateTime('occurred_at');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('marketing_email_feedback');Schema::dropIfExists('marketing_email_batches');}
};
