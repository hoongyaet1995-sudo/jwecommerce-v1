<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_configuration', function (Blueprint $table): void {
            $table->timestamp('mode_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_configuration', function (Blueprint $table): void {
            $table->dropColumn('mode_reset_at');
        });
    }
};
