<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketing_audio_assets',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignId('owner_id')->constrained('users');$t->string('name',100);$t->string('path');$t->string('sha256',64);$t->decimal('duration',9,3);$t->json('license');$t->boolean('authorized')->default(false);$t->timestamps();$t->index(['project_id','owner_id','authorized']);});
  Schema::create('marketing_packaging_requests',function(Blueprint $t){$t->id();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->uuid('request_id');$t->string('payload_hash',64);$t->foreignUuid('film_id')->constrained('marketing_video_films');$t->timestamps();$t->unique(['project_id','request_id']);});
 }
 public function down():void{Schema::dropIfExists('marketing_packaging_requests');Schema::dropIfExists('marketing_audio_assets');}
};
