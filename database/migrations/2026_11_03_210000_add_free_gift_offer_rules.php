<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('addon_deals',function(Blueprint $t){$t->string('kind')->default('addon')->index();$t->unsignedInteger('buy_quantity')->default(1);$t->unsignedInteger('gift_quantity')->default(1);});}
 public function down():void {Schema::table('addon_deals',fn(Blueprint $t)=>$t->dropColumn(['kind','buy_quantity','gift_quantity']));}
};
