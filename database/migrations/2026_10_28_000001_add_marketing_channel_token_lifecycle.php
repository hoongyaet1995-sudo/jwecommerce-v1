<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('marketing_channel_connections',function(Blueprint $t){$t->text('refresh_token')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('refresh_expires_at')->nullable();});}
 public function down():void {Schema::table('marketing_channel_connections',fn(Blueprint $t)=>$t->dropColumn(['refresh_token','expires_at','refresh_expires_at']));}
};
