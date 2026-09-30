<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_store_accounts', function (Blueprint $table): void {
            $table->boolean('shared_pool_takeover_enabled')->default(false);
        });

        // Keep the choice currently displayed to existing connected stores.
        DB::table('inventory_store_accounts')->whereNotNull('marketplace_store_id')
            ->where('management_status', 'managed')->update(['shared_pool_takeover_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('inventory_store_accounts', function (Blueprint $table): void {
            $table->dropColumn('shared_pool_takeover_enabled');
        });
    }
};
