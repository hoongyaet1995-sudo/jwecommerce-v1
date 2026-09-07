<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_order_runs', function (Blueprint $table) {
            $table->string('origin', 16)->default('manual');
            $table->index(['store_id', 'origin', 'id']);
        });
    }
    public function down(): void
    {
        Schema::table('marketplace_order_runs', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'origin', 'id']);
            $table->dropColumn('origin');
        });
    }
};
