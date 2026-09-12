<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('sandbox_inventory_attempts',function(Blueprint $table){
  $table->uuid('id')->primary();$table->char('request_hash',64);
  $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
  $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
  $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
  $table->string('reason',255);$table->string('status',24);$table->json('snapshot');
  $table->unsignedInteger('before_quantity')->nullable();$table->unsignedInteger('observed_quantity')->nullable();
  $table->string('error_code',64)->nullable();$table->timestamps();
  $table->index(['store_id','status']);
 });}
 public function down():void {Schema::dropIfExists('sandbox_inventory_attempts');}
};
