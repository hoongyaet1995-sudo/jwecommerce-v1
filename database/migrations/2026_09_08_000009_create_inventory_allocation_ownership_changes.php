<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('inventory_allocation_ownership_changes',function(Blueprint $table){
  $table->id();$table->foreignId('stock_allocation_id')->unique('inv_ownership_allocation_unique')->constrained('stock_allocations',indexName:'inv_ownership_allocation_fk')->restrictOnDelete();
  $table->foreignId('inventory_balance_id')->constrained('inventory_balances',indexName:'inv_ownership_balance_fk')->restrictOnDelete();
  $table->foreignId('actor_id')->constrained('users',indexName:'inv_ownership_actor_fk')->restrictOnDelete();$table->unsignedInteger('quantity');
  $table->unsignedBigInteger('balance_version');$table->string('reason',255);$table->dateTime('created_at');
 });}
 public function down():void {Schema::dropIfExists('inventory_allocation_ownership_changes');}
};
