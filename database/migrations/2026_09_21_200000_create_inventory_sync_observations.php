<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sync_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('listing_variant_id')->constrained('marketplace_listing_variants');
            $table->string('external_product_id')->nullable();
            $table->string('external_variant_id')->nullable();
            $table->uuid('sync_task_id')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestamp('observed_at');
            $table->index(['store_id', 'product_variant_id', 'id'], 'inventory_observation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_observations');
    }
};
