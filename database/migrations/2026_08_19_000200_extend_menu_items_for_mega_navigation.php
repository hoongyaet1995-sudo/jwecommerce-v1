<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('label_cn')->nullable()->after('label');
            $table->string('badge')->nullable()->after('label_cn');
            $table->string('badge_type', 32)->nullable()->after('badge');
            $table->string('featured_title')->nullable()->after('badge_type');
            $table->text('featured_description')->nullable()->after('featured_title');
            $table->string('featured_price')->nullable()->after('featured_description');
            $table->string('featured_image_url', 2048)->nullable()->after('featured_price');
            $table->string('featured_cta_label')->nullable()->after('featured_image_url');
            $table->string('featured_cta_url', 2048)->nullable()->after('featured_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn([
                'label_cn', 'badge', 'badge_type', 'featured_title', 'featured_description',
                'featured_price', 'featured_image_url', 'featured_cta_label', 'featured_cta_url',
            ]);
        });
    }
};
