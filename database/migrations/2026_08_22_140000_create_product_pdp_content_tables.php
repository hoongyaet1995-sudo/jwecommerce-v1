<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_pdp_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('highlights')->nullable();
            $table->json('specifications')->nullable();
            $table->boolean('use_store_default_shipping')->default(true);
            $table->json('shipping_policy')->nullable();
            $table->json('custom_accordions')->nullable();
            $table->decimal('rating_score', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->string('configurator_label')->nullable();
            $table->timestamps();
        });

        Schema::create('product_pdp_related_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['product_id', 'related_product_id'], 'pdp_related_product_unique');
        });

        Schema::create('product_pdp_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('author');
            $table->unsignedTinyInteger('rating');
            $table->string('review_date')->nullable();
            $table->string('title');
            $table->text('comment');
            $table->boolean('verified_buyer')->default(false);
            $table->string('variant_purchased')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pdp_reviews');
        Schema::dropIfExists('product_pdp_related_products');
        Schema::dropIfExists('product_pdp_contents');
    }
};
