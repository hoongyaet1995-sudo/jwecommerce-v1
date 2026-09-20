<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_store_order_lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_key', 160)->unique();
            $table->string('channel_key', 80);
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
            $table->unsignedInteger('cancelled_quantity')->default(0);
            $table->unsignedInteger('refunded_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->timestamps();
        });
        Schema::table('inventory_store_order_events', function (Blueprint $table) {
            $table->foreignId('order_line_id')->nullable()->constrained('inventory_store_order_lines')->restrictOnDelete();
            $table->string('payload_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_store_order_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_line_id');
            $table->dropColumn('payload_hash');
        });
        Schema::dropIfExists('inventory_store_order_lines');
    }
};
