<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::table('marketing_analytics_events',function(Blueprint $t){$t->json('details')->nullable();});}public function down():void{Schema::table('marketing_analytics_events',fn(Blueprint $t)=>$t->dropColumn('details'));}};
