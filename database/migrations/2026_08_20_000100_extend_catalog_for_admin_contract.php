<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
        Schema::table('collections', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->string('type')->default('manual');
            $table->string('match_type')->nullable();
            $table->json('dynamic_rules')->nullable();
            $table->string('status')->default('active');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('has_variants')->default(true);
            $table->json('options')->nullable();
            $table->json('tags')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->json('options')->nullable();
            $table->unsignedInteger('cost_price')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('image_url', 2048)->nullable();
            $table->boolean('enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['options', 'cost_price', 'weight_grams', 'barcode', 'image_url', 'enabled']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['has_variants', 'options', 'tags', 'seo_title', 'seo_description']));
        Schema::table('collections', fn (Blueprint $table) => $table->dropColumn(['description', 'type', 'match_type', 'dynamic_rules', 'status']));
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['icon', 'seo_title', 'seo_description', 'seo_keywords', 'sort_order']);
        });
    }
};
