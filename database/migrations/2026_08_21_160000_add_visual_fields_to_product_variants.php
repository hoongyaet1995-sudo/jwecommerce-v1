<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('visual_type', 16)->default('none')->after('image_url');
            $table->string('color_code', 16)->nullable()->after('visual_type');
            $table->string('option_image', 2048)->nullable()->after('color_code');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['visual_type', 'color_code', 'option_image']);
        });
    }
};
