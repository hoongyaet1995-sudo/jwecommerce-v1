<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_rebaseline_epochs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32)->index();
            $table->string('operation_key', 120)->unique();
            $table->unsignedBigInteger('configuration_version');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('review_snapshot')->nullable();
            $table->string('review_hash', 64)->nullable();
            $table->timestamp('cutoff_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_configuration', function (Blueprint $table): void {
            $table->uuid('active_rebaseline_epoch_id')->nullable()
                ->constrained('inventory_rebaseline_epochs')->restrictOnDelete();
        });

        Schema::create('inventory_rebaseline_destinations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('epoch_id');
            $table->foreign('epoch_id')->references('id')->on('inventory_rebaseline_epochs')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('listing_variant_id')->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->foreignId('provider_facility_id')->nullable()->constrained('marketplace_store_facilities')->restrictOnDelete();
            // A non-null route key keeps the no-warehouse destination unique in MySQL.
            $table->string('route_key', 160);
            $table->string('zero_status', 32)->default('pending');
            $table->json('zero_receipt')->nullable();
            $table->timestamp('zero_confirmed_at')->nullable();
            $table->string('reopen_status', 32)->default('paused');
            $table->timestamps();
            $table->unique(['epoch_id', 'listing_variant_id', 'route_key'], 'rebaseline_destination_route_unique');
        });

        Schema::create('inventory_rebaseline_legacy_balances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('epoch_id');
            $table->foreign('epoch_id')->references('id')->on('inventory_rebaseline_epochs')->restrictOnDelete();
            $table->foreignId('inventory_balance_id');
            $table->foreign('inventory_balance_id', 'rebaseline_legacy_balance_fk')
                ->references('id')->on('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('physical_stock')->nullable();
            $table->unsignedInteger('reserved_stock')->nullable();
            $table->unsignedInteger('safety_stock')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->unsignedBigInteger('balance_version');
            $table->dateTime('archived_at');
            $table->unique(['epoch_id', 'inventory_balance_id'], 'rebaseline_legacy_balance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_rebaseline_legacy_balances');
        Schema::dropIfExists('inventory_rebaseline_destinations');
        Schema::table('inventory_configuration', fn (Blueprint $table) => $table->dropConstrainedForeignId('active_rebaseline_epoch_id'));
        Schema::dropIfExists('inventory_rebaseline_epochs');
    }
};
