<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_catalog_runs', function (Blueprint $table) {
            $table->unsignedInteger('total_products')->nullable()->after('items_processed');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_catalog_runs', function (Blueprint $table) {
            $table->dropColumn('total_products');
        });
    }
};
