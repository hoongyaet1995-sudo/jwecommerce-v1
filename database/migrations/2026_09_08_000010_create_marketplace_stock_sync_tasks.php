<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_stock_sync_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 128)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('listing_variant_id')->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('desired_quantity');
            $table->unsignedBigInteger('balance_version');
            $table->unsignedBigInteger('route_version');
            $table->string('platform_location_id', 128);
            // Staged is intentionally non-executable until a separately authorized dispatcher is enabled.
            $table->string('status', 24)->default('staged');
            $table->json('request_snapshot');
            $table->json('result_snapshot')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['inventory_balance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_stock_sync_tasks');
    }
};
