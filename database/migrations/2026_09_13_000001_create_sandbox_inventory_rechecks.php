<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('sandbox_inventory_rechecks',function(Blueprint $table){
  $table->id();$table->uuid('attempt_id');
  $table->foreign('attempt_id')->references('id')->on('sandbox_inventory_attempts')->restrictOnDelete();
  $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
  $table->unsignedInteger('observed_quantity')->nullable();$table->string('outcome',32);$table->timestamp('created_at');
 });}
 public function down():void {Schema::dropIfExists('sandbox_inventory_rechecks');}
};
