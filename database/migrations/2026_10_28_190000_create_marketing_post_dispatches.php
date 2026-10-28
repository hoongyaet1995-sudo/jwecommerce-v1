<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('marketing_post_dispatches',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('post_id');$t->unsignedBigInteger('user_id');$t->unsignedInteger('revision');$t->string('state',30);$t->json('payload');$t->timestamp('due_at');$t->timestamp('next_check_at')->nullable();$t->unsignedInteger('checks')->default(0);$t->string('error_code',80)->nullable();$t->timestamps();$t->unique(['post_id','revision']);$t->index(['state','due_at']);$t->index(['state','next_check_at']);$t->foreign('post_id')->references('id')->on('marketing_posts')->cascadeOnDelete();$t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();}); }
 public function down():void {Schema::dropIfExists('marketing_post_dispatches');}
};
