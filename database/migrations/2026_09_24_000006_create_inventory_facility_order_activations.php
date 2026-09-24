<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_facility_order_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_store_id')->constrained('marketplace_stores', 'id', 'fac_order_store_fk')->restrictOnDelete();
            $table->foreignId('marketplace_store_facility_id')->constrained('marketplace_store_facilities', 'id', 'fac_order_facility_fk')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants', 'id', 'fac_order_variant_fk')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances', 'id', 'fac_order_balance_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', 'id', 'fac_order_actor_fk')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('orders_created_after');
            $table->dateTime('created_at');
            $table->unique(['marketplace_store_id', 'marketplace_store_facility_id', 'product_variant_id'],
                'facility_order_activation_identity');
        });
        Schema::table('stock_allocations', function (Blueprint $table): void {
            $table->foreignId('inventory_facility_order_activation_id')->nullable()
                ->constrained('inventory_facility_order_activations', 'id', 'stock_allocation_fac_order_fk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_allocations', function (Blueprint $table): void {
            $table->dropForeign('stock_allocation_fac_order_fk');
            $table->dropColumn('inventory_facility_order_activation_id');
        });
        Schema::dropIfExists('inventory_facility_order_activations');
    }
};
