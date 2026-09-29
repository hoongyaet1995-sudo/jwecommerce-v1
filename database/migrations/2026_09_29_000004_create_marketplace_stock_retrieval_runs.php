<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_stock_retrieval_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('selected_skus');
            $table->unsignedInteger('total_locations');
            $table->unsignedInteger('completed_locations')->default(0);
            $table->unsignedInteger('failed_locations')->default(0);
            $table->string('last_error_code', 100)->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('marketplace_stock_retrieval_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id');
            $table->foreign('run_id')->references('id')->on('marketplace_stock_retrieval_runs')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('marketplace_store_id')->nullable()->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('marketplace_store_facility_id')->nullable()
                ->constrained('marketplace_store_facilities', 'id', 'stock_retrieve_facility_fk')->restrictOnDelete();
            $table->string('location_key', 120);
            $table->string('status', 24)->default('pending');
            $table->string('action', 24)->nullable();
            $table->unsignedInteger('observed_quantity')->nullable();
            $table->unsignedInteger('previous_quantity')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'product_variant_id', 'location_key'], 'stock_retrieve_location_unique');
            $table->index(['run_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_stock_retrieval_items');
        Schema::dropIfExists('marketplace_stock_retrieval_runs');
    }
};
