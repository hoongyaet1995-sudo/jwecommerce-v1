<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_facility_transfer_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('product_variant_id')->constrained('product_variants', 'id', 'fac_transfer_variant_fk')->restrictOnDelete();
            $table->foreignId('source_facility_id')->constrained('marketplace_store_facilities', 'id', 'fac_transfer_source_fk')->restrictOnDelete();
            $table->foreignId('destination_facility_id')->constrained('marketplace_store_facilities', 'id', 'fac_transfer_dest_fk')->restrictOnDelete();
            $table->foreignId('source_balance_id')->constrained('inventory_balances', 'id', 'fac_transfer_src_bal_fk')->restrictOnDelete();
            $table->foreignId('destination_balance_id')->constrained('inventory_balances', 'id', 'fac_transfer_dst_bal_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'fac_transfer_actor_fk')->restrictOnDelete();
            $table->unsignedBigInteger('configuration_version');
            $table->unsignedBigInteger('source_topology_revision');
            $table->unsignedBigInteger('destination_topology_revision');
            $table->unsignedBigInteger('source_balance_version');
            $table->unsignedBigInteger('destination_balance_version');
            $table->unsignedInteger('quantity');
            $table->string('reason', 255);
            $table->string('status', 24)->default('pending');
            $table->string('operation_key', 120)->nullable()->unique('fac_transfer_operation_key_uq');
            $table->json('receipt')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_facility_transfer_reviews');
    }
};
