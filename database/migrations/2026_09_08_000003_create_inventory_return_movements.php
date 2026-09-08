<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('inventory_return_movements',function(Blueprint $table){
   $table->id();$table->foreignId('return_request_id')->unique()->constrained('return_requests')->restrictOnDelete();
   $table->foreignId('stock_allocation_id')->constrained('stock_allocations')->restrictOnDelete();
   $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
   $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();$table->unsignedInteger('quantity');
   $table->unsignedInteger('physical_after');$table->unsignedBigInteger('balance_version');$table->dateTime('created_at');
  });
 }
 public function down():void {Schema::dropIfExists('inventory_return_movements');}
};
