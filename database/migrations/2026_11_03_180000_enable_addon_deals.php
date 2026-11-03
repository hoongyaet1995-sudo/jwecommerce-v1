<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('addon_deals',fn(Blueprint $t)=>$t->boolean('enabled')->default(false));}
 public function down():void {Schema::table('addon_deals',fn(Blueprint $t)=>$t->dropColumn('enabled'));}
};
