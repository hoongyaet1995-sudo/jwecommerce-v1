<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('marketing_production_steps',fn(Blueprint $t)=>$t->uuid('authorization_id')->nullable()->unique());}
 public function down():void {Schema::table('marketing_production_steps',function(Blueprint $t){$t->dropUnique(['authorization_id']);$t->dropColumn('authorization_id');});}
};
