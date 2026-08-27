<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->unsignedBigInteger('configuration_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->dropColumn('configuration_version');
        });
    }
};
