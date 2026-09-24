<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_facility_stock_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('marketplace_store_id')->constrained('marketplace_stores', 'id', 'fac_stock_store_fk')->restrictOnDelete();
            $table->foreignId('marketplace_store_facility_id')->constrained('marketplace_store_facilities', 'id', 'fac_stock_facility_fk')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants', 'id', 'fac_stock_variant_fk')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances', 'id', 'fac_stock_balance_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'fac_stock_actor_fk')->restrictOnDelete();
            $table->string('kind', 24);
            $table->unsignedBigInteger('configuration_version');
            $table->unsignedBigInteger('topology_revision');
            $table->unsignedBigInteger('balance_version');
            $table->json('input');
            $table->json('before');
            $table->json('after');
            $table->string('status', 24)->default('pending');
            $table->string('operation_key', 120)->nullable()->unique('fac_stock_operation_key_uq');
            $table->json('receipt')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_facility_stock_reviews');
    }
};
