<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('marketing_projects',function(Blueprint $t){$t->string('template_id',80)->nullable()->index();$t->string('template_version',40)->nullable();$t->json('template_snapshot')->nullable();});
  Schema::create('marketing_template_grants',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('owner_id')->constrained('users');$t->unsignedBigInteger('product_id');$t->unsignedBigInteger('cap_microusd');$t->unsignedInteger('max_llm')->default(8);$t->unsignedInteger('max_tts')->default(4);$t->boolean('active')->default(false);$t->json('policy');$t->json('pricing');$t->timestamp('expires_at');$t->timestamps();});
  Schema::create('marketing_template_runs',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->foreignUuid('grant_id')->nullable()->constrained('marketing_template_grants');$t->uuid('request_id');$t->string('input_digest',64);$t->json('input');$t->json('draft')->nullable();$t->json('checks')->nullable();$t->json('quote')->nullable();$t->string('status',32)->default('created');$t->string('phase',48)->default('created');$t->unsignedTinyInteger('corrections')->default(0);$t->json('result')->nullable();$t->json('error')->nullable();$t->timestamps();$t->unique(['project_id','request_id']);});
  Schema::create('marketing_template_calls',function(Blueprint $t){$t->string('id',64)->primary();$t->foreignUuid('run_id')->constrained('marketing_template_runs');$t->foreignUuid('grant_id')->constrained('marketing_template_grants');$t->string('stage',48);$t->string('kind',8);$t->string('payload_hash',64);$t->uuid('client_request_id');$t->string('status',24);$t->unsignedBigInteger('reserved_microusd');$t->unsignedBigInteger('actual_microusd')->nullable();$t->json('receipt')->nullable();$t->timestamps();$t->unique(['run_id','stage']);});
 }
 public function down():void {Schema::dropIfExists('marketing_template_calls');Schema::dropIfExists('marketing_template_runs');Schema::dropIfExists('marketing_template_grants');Schema::table('marketing_projects',fn(Blueprint $t)=>$t->dropColumn(['template_id','template_version','template_snapshot']));}
};
