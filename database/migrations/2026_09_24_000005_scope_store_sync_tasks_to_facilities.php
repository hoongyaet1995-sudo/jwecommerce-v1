<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->foreignId('inventory_balance_id')->nullable()->after('product_variant_id')
                ->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('marketplace_store_facility_id')->nullable()->after('inventory_balance_id')
                ->constrained('marketplace_store_facilities', 'id', 'store_sync_facility_fk')->restrictOnDelete();
            $table->index(['marketplace_store_id', 'product_variant_id', 'marketplace_store_facility_id', 'status'],
                'store_sync_facility_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->dropIndex('store_sync_facility_status_idx');
            $table->dropForeign('store_sync_facility_fk');
            $table->dropColumn('marketplace_store_facility_id');
            $table->dropConstrainedForeignId('inventory_balance_id');
        });
    }
};
