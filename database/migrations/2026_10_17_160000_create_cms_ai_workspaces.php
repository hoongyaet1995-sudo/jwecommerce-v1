<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('cms_ai_workspaces',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('source_page',100);$t->unsignedInteger('version')->default(1);$t->longText('source_signature');$t->json('state');$t->timestamp('discarded_at')->nullable();$t->timestamps();$t->unique(['user_id','source_page']);});
  Schema::create('cms_ai_tasks',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedBigInteger('user_id')->index();$t->string('action',20);$t->char('request_hash',64);$t->string('status',20);$t->string('model',150)->nullable();$t->json('response')->nullable();$t->unsignedSmallInteger('response_status')->nullable();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('cms_ai_tasks');Schema::dropIfExists('cms_ai_workspaces');}
};
