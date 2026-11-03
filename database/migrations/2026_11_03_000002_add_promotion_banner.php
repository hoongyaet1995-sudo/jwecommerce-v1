<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('promotion_offers',function(Blueprint $t){$t->text('cover_image')->nullable();$t->json('image_settings')->nullable();});}
 public function down():void {Schema::table('promotion_offers',fn(Blueprint $t)=>$t->dropColumn(['cover_image','image_settings']));}
};
