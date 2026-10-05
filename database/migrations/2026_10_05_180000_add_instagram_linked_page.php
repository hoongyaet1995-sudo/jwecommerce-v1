<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('managed_social_accounts',fn(Blueprint $t)=>$t->string('linked_page_id',100)->nullable());}
 public function down():void {Schema::table('managed_social_accounts',fn(Blueprint $t)=>$t->dropColumn('linked_page_id'));}
};
