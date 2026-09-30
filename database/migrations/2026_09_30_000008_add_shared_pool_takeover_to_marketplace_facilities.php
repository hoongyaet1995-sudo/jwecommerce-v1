<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_store_facilities', function (Blueprint $table): void {
            $table->boolean('shared_pool_takeover_enabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_store_facilities', function (Blueprint $table): void {
            $table->dropColumn('shared_pool_takeover_enabled');
        });
    }
};
