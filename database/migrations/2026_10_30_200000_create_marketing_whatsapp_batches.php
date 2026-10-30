<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_whatsapp_batches',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('campaign_id');$t->string('merchant_key',100);$t->unsignedBigInteger('user_id');$t->unsignedInteger('campaign_version');$t->string('state',24);$t->unsignedInteger('total');$t->unsignedInteger('excluded');$t->unsignedInteger('cursor')->default(0);$t->longText('payload');$t->string('error_code',80)->nullable();$t->timestamp('confirmed_at')->nullable();$t->timestamp('not_before')->nullable();$t->dateTime('expires_at');$t->timestamps();$t->unique(['campaign_id','campaign_version'],'marketing_wa_batch_once');});
  Schema::create('marketing_whatsapp_deliveries',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('batch_id')->index();$t->string('merchant_key',100);$t->unsignedBigInteger('account_id');$t->text('recipient');$t->string('recipient_hash',64);$t->string('state',24);$t->string('provider_message_id',512)->nullable();$t->string('error_code',80)->nullable();$t->timestamps();$t->unique(['batch_id','recipient_hash'],'marketing_wa_recipient_once');$t->index(['account_id','state']);});
 }
 public function down():void{Schema::dropIfExists('marketing_whatsapp_deliveries');Schema::dropIfExists('marketing_whatsapp_batches');}
};
