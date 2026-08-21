<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('link_type', 32)->default('custom_url');
            $table->string('target_id')->nullable();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_external')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->string('display_style', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn([
            'link_type', 'target_id', 'icon', 'description', 'is_external', 'is_active', 'is_highlighted', 'display_style',
        ]));
    }
};
