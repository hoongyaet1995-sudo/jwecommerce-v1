<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_stock_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 160)->unique();
            $table->string('request_hash', 64);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('physical_before')->nullable();
            $table->unsignedInteger('physical_after');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 64);
            $table->json('receipt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_stock_operations');
    }
};
