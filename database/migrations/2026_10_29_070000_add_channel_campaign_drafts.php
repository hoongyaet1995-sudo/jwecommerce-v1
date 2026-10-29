<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('marketing_campaigns',function(Blueprint $t){$t->string('channel',20)->default('post');$t->json('channel_document')->nullable();});}
 public function down():void {Schema::table('marketing_campaigns',fn(Blueprint $t)=>$t->dropColumn(['channel','channel_document']));}
};
