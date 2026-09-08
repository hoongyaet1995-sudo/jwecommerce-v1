<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('inventory_count_reviews',function(Blueprint $table){
  $table->string('operation_kind',16)->default('count');$table->integer('quantity_delta')->nullable();
  $table->unsignedBigInteger('configuration_version')->nullable();
 });}
 public function down():void {Schema::table('inventory_count_reviews',fn(Blueprint $table)=>$table->dropColumn(['operation_kind','quantity_delta','configuration_version']));}
};
