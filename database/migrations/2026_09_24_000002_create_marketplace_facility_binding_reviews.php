<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_facility_binding_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('marketplace_store_id')->constrained('marketplace_stores', 'id', 'fac_bind_store_fk')->restrictOnDelete();
            $table->foreignId('marketplace_store_facility_id')->constrained('marketplace_store_facilities', 'id', 'fac_bind_facility_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'fac_bind_actor_fk')->restrictOnDelete();
            $table->foreignId('before_warehouse_id')->nullable()->constrained('inventory_warehouses', 'id', 'fac_bind_before_wh_fk')->restrictOnDelete();
            $table->foreignId('after_warehouse_id')->constrained('inventory_warehouses', 'id', 'fac_bind_after_wh_fk')->restrictOnDelete();
            $table->unsignedBigInteger('topology_revision');
            $table->unsignedBigInteger('warehouse_version');
            $table->string('reason', 255);
            $table->string('status', 24)->default('pending');
            $table->string('operation_key', 120)->nullable()->unique('fac_bind_operation_key_uq');
            $table->json('receipt')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_facility_binding_reviews');
    }
};
