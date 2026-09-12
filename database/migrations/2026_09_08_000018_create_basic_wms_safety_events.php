<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('basic_wms_safety_events',function(Blueprint $table){
  $table->uuid('operation_key')->primary();
  $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
  $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
  $table->string('request_hash',64);$table->json('receipt');$table->timestamp('created_at');
 });}
 public function down():void {Schema::dropIfExists('basic_wms_safety_events');}
};
