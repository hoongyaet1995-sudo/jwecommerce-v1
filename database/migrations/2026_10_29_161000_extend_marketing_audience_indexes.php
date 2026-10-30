<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('marketing_audience_sources',function(Blueprint $t){$t->json('tags')->nullable();$t->index(['contact_id','active','kind'],'audience_source_activity');});Schema::table('marketing_audience_tasks',fn(Blueprint $t)=>$t->index(['merchant_key','kind','state'],'audience_task_state'));}
 public function down():void{Schema::table('marketing_audience_sources',function(Blueprint $t){$t->dropIndex('audience_source_activity');$t->dropColumn('tags');});Schema::table('marketing_audience_tasks',fn(Blueprint $t)=>$t->dropIndex('audience_task_state'));}
};
