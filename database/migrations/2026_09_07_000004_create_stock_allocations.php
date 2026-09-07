<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->string('line_key', 100);
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 16);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'line_key'], 'stock_allocation_owner_unique');
            $table->index(['product_variant_id', 'status']);
        });
        Schema::create('stock_allocation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_allocation_id')->constrained()->restrictOnDelete();
            $table->string('event', 16);
            $table->integer('available_delta');
            $table->integer('available_after');
            $table->timestamp('created_at');
            $table->unique(['stock_allocation_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_allocation_events');
        Schema::dropIfExists('stock_allocations');
    }
};
