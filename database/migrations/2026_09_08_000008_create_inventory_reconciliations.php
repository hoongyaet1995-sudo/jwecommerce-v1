<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('inventory_reconciliations',function(Blueprint $table){
  $table->id();$table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
  $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();$table->string('operation_key',128)->unique();
  $table->string('request_hash',64);$table->json('before');$table->json('after');$table->string('reason',255);$table->dateTime('created_at');
 });}
 public function down():void {Schema::dropIfExists('inventory_reconciliations');}
};
