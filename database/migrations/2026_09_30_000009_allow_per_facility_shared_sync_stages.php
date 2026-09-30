<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->index('operation_id', 'inventory_store_sync_operation_fk_idx');
            $table->dropUnique('inventory_store_sync_stage_unique');
            $table->unique(['operation_id', 'marketplace_store_id', 'stage', 'marketplace_store_facility_id'], 'inventory_store_sync_facility_stage_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->unique(['operation_id', 'marketplace_store_id', 'stage'], 'inventory_store_sync_stage_unique');
            $table->dropUnique('inventory_store_sync_facility_stage_unique');
            $table->dropIndex('inventory_store_sync_operation_fk_idx');
        });
    }
};
