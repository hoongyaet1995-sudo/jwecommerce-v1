<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id(); $table->string('slug')->unique(); $table->string('name'); $table->string('name_cn')->nullable(); $table->string('image_url')->nullable(); $table->text('description')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('collections', function (Blueprint $table): void {
            $table->id(); $table->string('slug')->unique(); $table->string('title'); $table->string('subtitle')->nullable(); $table->string('cover_image')->nullable(); $table->string('badge')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id(); $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); $table->string('handle')->unique(); $table->string('title'); $table->string('subtitle')->nullable(); $table->string('brand')->nullable(); $table->text('description')->nullable(); $table->unsignedInteger('base_price'); $table->unsignedInteger('compare_at_price')->nullable(); $table->json('features')->nullable(); $table->string('status')->default('draft'); $table->boolean('is_featured')->default(false); $table->timestamps();
        });
        Schema::create('collection_product', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->primary(['collection_id', 'product_id']);
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->string('sku')->unique(); $table->string('title'); $table->string('color_name')->nullable(); $table->string('color_hex', 16)->nullable(); $table->string('size')->nullable(); $table->string('material')->nullable(); $table->unsignedInteger('price'); $table->unsignedInteger('compare_at_price')->nullable(); $table->unsignedInteger('stock')->default(0); $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete(); $table->string('path'); $table->string('alt')->nullable(); $table->unsignedSmallInteger('position')->default(0); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); $table->string('email')->unique(); $table->string('first_name')->nullable(); $table->string('last_name')->nullable(); $table->string('phone')->nullable(); $table->timestamps();
        });
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('discount_type'); $table->unsignedInteger('value'); $table->unsignedInteger('min_spend')->nullable(); $table->boolean('is_active')->default(true); $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->timestamps();
        });
        Schema::create('carts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $table->string('session_key')->nullable()->unique(); $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete(); $table->string('currency', 3)->default('USD'); $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('cart_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_variant_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('quantity'); $table->timestamps(); $table->unique(['cart_id', 'product_variant_id']);
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id(); $table->string('number')->unique(); $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $table->string('email'); $table->string('currency', 3)->default('USD'); $table->string('status')->default('pending_payment'); $table->unsignedInteger('subtotal'); $table->unsignedInteger('discount_amount')->default(0); $table->unsignedInteger('shipping_amount')->default(0); $table->unsignedInteger('tax_amount')->default(0); $table->unsignedInteger('total'); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_variant_id')->nullable()->nullOnDelete(); $table->string('sku'); $table->string('title'); $table->string('variant_title')->nullable(); $table->string('image_url')->nullable(); $table->unsignedInteger('unit_price'); $table->unsignedInteger('quantity'); $table->unsignedInteger('line_total'); $table->timestamps();
        });
        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->string('type'); $table->string('first_name'); $table->string('last_name'); $table->string('phone')->nullable(); $table->string('line_1'); $table->string('line_2')->nullable(); $table->string('city'); $table->string('state')->nullable(); $table->string('postal_code'); $table->string('country'); $table->timestamps();
        });
        Schema::create('order_payments', function (Blueprint $table): void {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->string('gateway')->default('simulated'); $table->string('status')->default('pending'); $table->string('reference')->nullable(); $table->json('payload')->nullable(); $table->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->string('from_status')->nullable(); $table->string('to_status'); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('note')->nullable(); $table->timestamps();
        });
        Schema::create('menus', function (Blueprint $table): void {
            $table->id(); $table->string('key')->unique(); $table->string('name'); $table->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('menu_id')->constrained()->cascadeOnDelete(); $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete(); $table->string('label'); $table->string('url'); $table->unsignedSmallInteger('position')->default(0); $table->timestamps();
        });
        Schema::create('pages', function (Blueprint $table): void {
            $table->id(); $table->string('slug')->unique(); $table->string('title'); $table->boolean('is_published')->default(false); $table->timestamps();
        });
        Schema::create('page_sections', function (Blueprint $table): void {
            $table->id(); $table->foreignId('page_id')->constrained()->cascadeOnDelete(); $table->string('type'); $table->json('content'); $table->unsignedSmallInteger('position')->default(0); $table->timestamps();
        });
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id(); $table->string('key')->unique(); $table->json('value')->nullable(); $table->timestamps();
        });
        Schema::create('social_links', function (Blueprint $table): void {
            $table->id(); $table->string('platform')->unique(); $table->string('url'); $table->boolean('is_active')->default(true); $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['social_links','store_settings','page_sections','pages','menu_items','menus','order_status_histories','order_payments','order_addresses','order_items','orders','coupons','cart_items','carts','customers','product_images','product_variants','collection_product','products','collections','categories'] as $table) { Schema::dropIfExists($table); }
    }
};
