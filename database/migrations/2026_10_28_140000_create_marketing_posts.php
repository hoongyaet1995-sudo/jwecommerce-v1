<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_posts',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained('users');$t->string('merchant_key',100);$t->unsignedInteger('version')->default(0);$t->string('status',32)->default('Draft');$t->timestamp('scheduled_at')->nullable();$t->string('timezone',64)->default('Asia/Kuala_Lumpur');$t->json('document');$t->timestamps();$t->index(['user_id','scheduled_at']);});
  Schema::create('marketing_post_attempts',function(Blueprint $t){$t->id();$t->foreignUuid('post_id')->constrained('marketing_posts');$t->unsignedInteger('revision');$t->uuid('connection_id');$t->string('state',32);$t->timestamps();$t->unique(['post_id','revision','connection_id'],'marketing_post_attempt_unique');});
 }
 public function down():void{Schema::dropIfExists('marketing_post_attempts');Schema::dropIfExists('marketing_posts');}
};
