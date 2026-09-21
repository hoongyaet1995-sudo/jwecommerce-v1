<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->foreignId('listing_variant_id')->nullable()->after('product_variant_id')
                ->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->string('provider_external_product_id', 160)->nullable()->after('listing_variant_id');
            $table->string('provider_external_variant_id', 160)->nullable()->after('provider_external_product_id');
            $table->string('provider_location_id', 120)->nullable()->after('listing_variant_id');
        });
        Schema::create('inventory_store_sync_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source', 20);
            $table->unsignedInteger('target_quantity')->nullable();
            $table->json('provider_receipt')->nullable();
            $table->timestamp('created_at');

            $table->foreign('task_id')->references('id')->on('inventory_store_sync_tasks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_store_sync_attempts');
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('listing_variant_id');
            $table->dropColumn(['provider_external_product_id', 'provider_external_variant_id', 'provider_location_id']);
        });
    }
};
