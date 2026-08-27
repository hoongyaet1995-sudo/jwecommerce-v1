<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('package_length_cm', 8, 2)->nullable()->after('weight_grams');
            $table->decimal('package_width_cm', 8, 2)->nullable()->after('package_length_cm');
            $table->decimal('package_height_cm', 8, 2)->nullable()->after('package_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['package_length_cm', 'package_width_cm', 'package_height_cm']);
        });
    }
};
