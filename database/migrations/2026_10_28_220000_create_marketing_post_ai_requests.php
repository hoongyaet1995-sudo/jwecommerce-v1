<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('marketing_post_ai_requests',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedBigInteger('user_id')->index();$t->string('merchant_key');$t->string('state',24);$t->string('model');$t->string('fingerprint',64);$t->unsignedInteger('cap_microusd');$t->unsignedInteger('actual_microusd')->nullable();$t->json('payload');$t->json('result')->nullable();$t->string('error_code')->nullable();$t->timestamp('expires_at');$t->timestamps();});}
 public function down():void{Schema::dropIfExists('marketing_post_ai_requests');}
};
