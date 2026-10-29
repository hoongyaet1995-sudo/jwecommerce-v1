<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketing_content_templates',function(Blueprint $t){$t->uuid('id')->primary();$t->string('merchant_key',100);$t->foreignId('user_id')->constrained();$t->string('channel',20);$t->unsignedBigInteger('account_id')->nullable();$t->string('waba_id',100)->nullable();$t->string('name',120);$t->string('language',12)->default('en');$t->string('category',20)->nullable();$t->string('state',30)->default('Draft');$t->string('provider_id',100)->nullable();$t->string('provider_code',100)->nullable();$t->json('document');$t->unsignedInteger('version')->default(1);$t->timestamps();$t->index(['merchant_key','user_id','channel']);$t->index(['account_id','waba_id']);});}
 public function down():void {Schema::dropIfExists('marketing_content_templates');}
};
