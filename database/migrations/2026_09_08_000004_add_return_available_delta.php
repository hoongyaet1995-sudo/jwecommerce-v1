<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_return_movements', function (Blueprint $table) {
            $table->unsignedInteger('available_delta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_return_movements', fn (Blueprint $table) => $table->dropColumn('available_delta'));
    }
};
