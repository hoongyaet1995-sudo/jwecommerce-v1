<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_facility_ledger_migration_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('marketplace_store_id')->constrained('marketplace_stores', 'id', 'fac_mig_store_fk')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants', 'id', 'fac_mig_variant_fk')->restrictOnDelete();
            $table->foreignId('source_balance_id')->constrained('inventory_balances', 'id', 'fac_mig_balance_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'fac_mig_actor_fk')->restrictOnDelete();
            $table->unsignedBigInteger('configuration_version');
            $table->unsignedBigInteger('topology_revision');
            $table->unsignedBigInteger('source_version');
            $table->json('physical_by_provider_id');
            $table->json('facility_bindings');
            $table->string('reason', 255);
            $table->string('status', 24)->default('pending');
            $table->string('operation_key', 120)->nullable()->unique('fac_mig_operation_key_uq');
            $table->json('receipt')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_facility_ledger_migration_reviews');
    }
};
