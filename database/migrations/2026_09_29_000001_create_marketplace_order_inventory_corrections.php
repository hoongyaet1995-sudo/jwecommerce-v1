<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_order_inventory_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_allocation_id')->unique('moic_allocation_unique')
                ->constrained('stock_allocations', indexName: 'moic_allocation_fk')->restrictOnDelete();
            $table->foreignId('from_balance_id')->constrained('inventory_balances', indexName: 'moic_from_balance_fk')->restrictOnDelete();
            $table->foreignId('to_balance_id')->constrained('inventory_balances', indexName: 'moic_to_balance_fk')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users', indexName: 'moic_actor_fk')->restrictOnDelete();
            $table->string('provider_warehouse_code', 100);
            $table->string('allocation_status', 16);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_inventory_corrections');
    }
};
