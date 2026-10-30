<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('marketing_audience_segments',function(Blueprint $t){$t->text('description')->nullable();$t->string('status',20)->default('active');});}
 public function down():void {Schema::table('marketing_audience_segments',fn(Blueprint $t)=>$t->dropColumn(['description','status']));}
};
