<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_order_activations',function(Blueprint $table){
            $table->id();
            $table->string('channel_key',64);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('orders_created_after');
            $table->dateTime('created_at');
            $table->unique(['channel_key','product_variant_id'],'inventory_order_activation_channel_variant');
        });
        Schema::table('stock_allocations',function(Blueprint $table){
            $table->foreignId('inventory_order_activation_id')->nullable()->constrained('inventory_order_activations')->restrictOnDelete();
        });
        Schema::table('order_items',fn(Blueprint $table)=>$table->foreignId('inventory_order_activation_id')->nullable()->constrained('inventory_order_activations')->restrictOnDelete());
        Schema::table('orders',fn(Blueprint $table)=>$table->string('inventory_error',64)->nullable());
    }
    public function down(): void {
        Schema::table('orders',fn(Blueprint $table)=>$table->dropColumn('inventory_error'));
        Schema::table('order_items',fn(Blueprint $table)=>$table->dropConstrainedForeignId('inventory_order_activation_id'));
        Schema::table('stock_allocations',fn(Blueprint $table)=>$table->dropConstrainedForeignId('inventory_order_activation_id'));
        Schema::dropIfExists('inventory_order_activations');
    }
};
