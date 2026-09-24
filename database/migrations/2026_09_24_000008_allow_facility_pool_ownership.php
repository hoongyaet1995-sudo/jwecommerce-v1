<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexNames = array_column(Schema::getIndexes('inventory_pools'), 'name');
        if (in_array('inventory_pool_kind_warehouse_unique', $indexNames, true)) {
            return;
        }
        Schema::table('inventory_pools', function (Blueprint $table): void {
            $table->index('warehouse_id', 'inventory_pools_warehouse_id_index');
        });
        Schema::table('inventory_pools', function (Blueprint $table): void {
            $table->dropUnique('inventory_pools_warehouse_id_unique');
            $table->unique(['kind', 'warehouse_id'], 'inventory_pool_kind_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_pools', function (Blueprint $table): void {
            $table->dropUnique('inventory_pool_kind_warehouse_unique');
            $table->unique('warehouse_id', 'inventory_pools_warehouse_id_unique');
            $table->dropIndex('inventory_pools_warehouse_id_index');
        });
    }
};
