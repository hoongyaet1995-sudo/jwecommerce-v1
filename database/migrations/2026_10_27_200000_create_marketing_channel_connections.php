<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('marketing_channel_connections',function(Blueprint $t){$t->uuid('id')->primary();$t->string('merchant_key',100);$t->string('platform',16);$t->string('provider_account_id',100);$t->string('linked_page_id',100)->nullable();$t->string('app_id',100);$t->string('name');$t->text('access_token');$t->json('permissions');$t->json('tasks')->nullable();$t->foreignId('authorized_by')->constrained('users');$t->string('state',32)->default('unverified');$t->unsignedInteger('version')->default(1);$t->timestamp('checked_at')->nullable();$t->string('check_code',64)->nullable();$t->timestamps();$t->unique(['merchant_key','platform','provider_account_id'],'marketing_channel_identity_unique');});}
 public function down():void{Schema::dropIfExists('marketing_channel_connections');}
};
