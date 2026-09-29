<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_current_stock_cutovers', function (Blueprint $table): void {
            $table->string('id', 100)->primary();
            $table->string('database_name', 128);
            $table->char('backup_sha256', 64);
            $table->dateTime('cutover_at');
            $table->string('status', 24);
            $table->dateTime('created_at');
        });
        Schema::create('inventory_current_stock_historical_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('channel_key', 80);
            $table->string('order_reference', 100);
            $table->string('cutover_id', 100);
            $table->foreign('cutover_id')->references('id')->on('inventory_current_stock_cutovers')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->unique(['channel_key', 'order_reference'], 'current_stock_historical_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_current_stock_historical_orders');
        Schema::dropIfExists('inventory_current_stock_cutovers');
    }
};
