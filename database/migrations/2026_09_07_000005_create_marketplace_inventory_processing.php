<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_inventory_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained('marketplace_stores')->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('orders_created_after')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('marketplace_order_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('marketplace_orders')->restrictOnDelete();
            $table->unsignedBigInteger('platform_updated_at');
            $table->string('status', 24);
            $table->string('terminal_status', 24)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_inventory');
        Schema::dropIfExists('marketplace_inventory_policies');
    }
};
