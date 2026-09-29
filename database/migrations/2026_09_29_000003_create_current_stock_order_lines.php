<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_current_stock_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('channel_key', 80);
            $table->string('order_reference', 100);
            $table->string('item_reference', 100);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('deducted_quantity')->default(0);
            $table->unsignedInteger('restored_quantity')->default(0);
            $table->boolean('terminal')->default(false);
            $table->timestamps();
            $table->unique(['channel_key', 'order_reference', 'item_reference'], 'current_stock_order_line_identity');
        });
        Schema::create('inventory_current_stock_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_line_id')->constrained('inventory_current_stock_order_lines')->restrictOnDelete();
            $table->string('event_key', 160)->unique();
            $table->char('request_hash', 64);
            $table->json('result');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_current_stock_order_events');
        Schema::dropIfExists('inventory_current_stock_order_lines');
    }
};
