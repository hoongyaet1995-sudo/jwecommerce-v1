<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_pools', function (Blueprint $table): void {
            // Keep an index for the warehouse FK before replacing its original unique key.
            $table->index('warehouse_id', 'inventory_pools_warehouse_id_index');
        });
        Schema::table('inventory_pools', function (Blueprint $table): void {
            $table->dropUnique('inventory_pools_warehouse_id_unique');
            $table->unique(['kind', 'warehouse_id'], 'inventory_pool_kind_warehouse_unique');
        });
        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->uuid('rebaseline_epoch_id')->nullable()
                ->constrained('inventory_rebaseline_epochs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', fn (Blueprint $table) =>
            $table->dropConstrainedForeignId('rebaseline_epoch_id'));
        Schema::table('inventory_pools', function (Blueprint $table): void {
            $table->dropUnique('inventory_pool_kind_warehouse_unique');
            $table->unique('warehouse_id', 'inventory_pools_warehouse_id_unique');
            $table->dropIndex('inventory_pools_warehouse_id_index');
        });
    }
};
