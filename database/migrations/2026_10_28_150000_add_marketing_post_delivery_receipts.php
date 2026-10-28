<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('marketing_post_attempts',fn(Blueprint $t)=>$t->json('receipt')->nullable());}public function down():void{Schema::table('marketing_post_attempts',fn(Blueprint $t)=>$t->dropColumn('receipt'));}};
