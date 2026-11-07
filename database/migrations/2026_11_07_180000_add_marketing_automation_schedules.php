<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('marketing_post_automations',function(Blueprint $t){$t->boolean('schedule_enabled')->default(false);$t->timestamp('schedule_epoch')->nullable();$t->timestamp('schedule_checked_at')->nullable();});
  Schema::create('marketing_automation_occurrences',function(Blueprint $t){
   $t->uuid('id')->primary();$t->foreignUuid('automation_id')->constrained('marketing_post_automations')->cascadeOnDelete();$t->timestamp('scheduled_at');$t->string('local_label');$t->unsignedInteger('version');$t->json('settings');$t->string('status');$t->string('reason')->nullable();$t->uuid('generation_id')->unique();$t->timestamp('started_at')->nullable();$t->timestamp('finished_at')->nullable();$t->timestamps();$t->unique(['automation_id','scheduled_at'],'marketing_occurrence_slot');$t->index(['status','scheduled_at']);
  });
 }
 public function down():void {Schema::dropIfExists('marketing_automation_occurrences');Schema::table('marketing_post_automations',fn(Blueprint $t)=>$t->dropColumn(['schedule_enabled','schedule_epoch','schedule_checked_at']));}
};
