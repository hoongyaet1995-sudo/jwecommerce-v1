<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('marketplace_inventory_items',function(Blueprint $table){
   $table->id();
   $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
   $table->string('line_key',100);
   $table->string('external_product_id',100); $table->string('external_variant_id',100);
   $table->string('warehouse_code',100); $table->string('status',30);
   $table->unsignedBigInteger('platform_updated_at');
   $table->foreignId('stock_allocation_id')->nullable()->constrained('stock_allocations')->restrictOnDelete();
   $table->timestamps(); $table->unique(['order_id','line_key'],'marketplace_inventory_item_identity');
  });
 }
 public function down():void { Schema::dropIfExists('marketplace_inventory_items'); }
};
