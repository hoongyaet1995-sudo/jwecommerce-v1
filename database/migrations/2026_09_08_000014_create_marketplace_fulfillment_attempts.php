<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketplace_fulfillment_attempts',function(Blueprint $table) {
   $table->id();
   $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
   $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
   $table->uuid('request_key')->unique();
   $table->string('operation',30);
   $table->json('item_ids');
   $table->json('request');
   $table->string('status',30);
   $table->json('confirmed_result')->nullable();
   $table->timestamps();
   $table->index(['order_id','operation','status']);
  });
 }
 public function down():void { Schema::dropIfExists('marketplace_fulfillment_attempts'); }
};
