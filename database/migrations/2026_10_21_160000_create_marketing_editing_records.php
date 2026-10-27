<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_clip_analyses',function(Blueprint $t){
   $t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->foreignUuid('clip_id')->constrained('marketing_video_clips');$t->string('fingerprint',64);$t->string('status',20);$t->json('evidence')->nullable();$t->json('usage')->nullable();$t->uuid('run_id')->nullable();$t->text('error_message')->nullable();$t->dateTime('started_at')->nullable();$t->timestamps();$t->unique(['project_id','fingerprint'],'marketing_clip_analysis_source');
  });
  Schema::create('marketing_edit_plans',function(Blueprint $t){
   $t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->foreignUuid('message_id')->unique()->constrained('marketing_conversation_messages');$t->foreignUuid('base_film_id')->constrained('marketing_video_films');$t->string('base_sha256',64);$t->string('status',20);$t->json('candidate');$t->json('recipe');$t->json('analysis_ids');$t->string('fingerprint',64);$t->uuid('accepted_request_id')->nullable();$t->foreignUuid('film_id')->nullable()->constrained('marketing_video_films');$t->timestamps();$t->unique(['project_id','accepted_request_id'],'marketing_edit_adoption_request');$t->index(['project_id','base_film_id','status']);
  });
  Schema::table('marketing_conversation_messages',fn(Blueprint $t)=>$t->json('context')->nullable());
 }
 public function down():void {Schema::table('marketing_conversation_messages',fn(Blueprint $t)=>$t->dropColumn('context'));Schema::dropIfExists('marketing_edit_plans');Schema::dropIfExists('marketing_clip_analyses');}
};
