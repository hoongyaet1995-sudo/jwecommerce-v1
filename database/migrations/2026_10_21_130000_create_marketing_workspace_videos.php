<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('marketing_video_clips',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->foreignUuid('revision_id')->constrained('marketing_revisions');$t->uuid('request_id');$t->unsignedTinyInteger('scene_number');$t->string('fingerprint',64);$t->string('status',24);$t->json('input');$t->json('quote');$t->json('result')->nullable();$t->string('connection_fingerprint',64);$t->foreignUuid('origin_run_id')->nullable()->unique()->constrained('marketing_ai_runs');$t->string('provider_task_id',160)->nullable()->unique();$t->string('raw_path')->nullable();$t->string('raw_sha256',64)->nullable();$t->string('output_path')->nullable();$t->string('output_sha256',64)->nullable();$t->string('error_code',80)->nullable();$t->text('error_message')->nullable();$t->dateTime('authorized_at')->nullable();$t->dateTime('expires_at');$t->timestamps();$t->unique(['project_id','request_id']);$t->index(['project_id','scene_number','fingerprint']);});
  Schema::create('marketing_video_films',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->foreignUuid('revision_id')->constrained('marketing_revisions');$t->string('fingerprint',64);$t->string('status',24);$t->json('input');$t->json('result')->nullable();$t->string('output_path')->nullable();$t->string('output_sha256',64)->nullable();$t->text('error_message')->nullable();$t->dateTime('approved_at')->nullable();$t->string('approved_sha256',64)->nullable();$t->timestamps();$t->unique(['project_id','fingerprint']);});
 }
 public function down():void{Schema::dropIfExists('marketing_video_films');Schema::dropIfExists('marketing_video_clips');}
};
