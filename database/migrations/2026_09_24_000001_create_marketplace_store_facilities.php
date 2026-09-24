<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_store_facility_scans', function (Blueprint $table): void {
            $table->foreignId('marketplace_store_id')->primary()->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('status', 24)->default('verification_pending');
            $table->string('topology', 24)->nullable();
            $table->string('ledger_migration_status', 24)->default('pending');
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_store_facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('inventory_warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->string('provider_facility_id', 120);
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique(['marketplace_store_id', 'provider_facility_id'], 'store_facility_identity_unique');
            $table->unique(['marketplace_store_id', 'inventory_warehouse_id'], 'store_physical_facility_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_store_facilities');
        Schema::dropIfExists('marketplace_store_facility_scans');
    }
};
