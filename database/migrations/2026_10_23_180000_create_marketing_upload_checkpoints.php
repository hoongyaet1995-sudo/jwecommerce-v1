<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketing_upload_checkpoints',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('project_id')->constrained('marketing_projects');$t->foreignUuid('plan_id')->constrained('marketing_production_plans');$t->uuid('scene_id');$t->string('role',32);$t->string('claim',64)->unique();$t->string('grant_reference');$t->string('connection',64);$t->string('sha256',64);$t->uuid('asset_id');$t->string('status',24);$t->json('evidence');$t->unsignedBigInteger('actual_microusd')->nullable();$t->timestamps();$t->index(['project_id','grant_reference']);});}
 public function down():void {Schema::dropIfExists('marketing_upload_checkpoints');}
};
