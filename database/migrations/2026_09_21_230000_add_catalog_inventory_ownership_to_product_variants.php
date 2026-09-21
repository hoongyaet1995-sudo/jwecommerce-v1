<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->timestamp('inventory_initialized_at')->nullable();
            $table->timestamp('first_physical_verified_at')->nullable();
            $table->string('first_physical_verification_operation', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_initialized_at',
                'first_physical_verified_at',
                'first_physical_verification_operation',
            ]);
        });
    }
};
