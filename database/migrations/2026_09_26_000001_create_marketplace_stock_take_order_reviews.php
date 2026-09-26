<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_stock_take_order_reviews')) {
            if (\Illuminate\Support\Facades\DB::table('marketplace_stock_take_order_reviews')->exists()) {
                throw new RuntimeException('A partially created Stock Take review table contains data. Repair it manually.');
            }
            Schema::drop('marketplace_stock_take_order_reviews');
        }
        Schema::create('marketplace_stock_take_order_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_store_id');
            $table->foreignId('marketplace_store_facility_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('order_id');
            $table->string('line_key', 100);
            $table->foreignId('actor_id');
            $table->string('decision', 32);
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['order_id', 'line_key'], 'stock_take_order_review_line');
            $table->foreign('marketplace_store_id', 'stkr_store_fk')->references('id')->on('marketplace_stores')->restrictOnDelete();
            $table->foreign('marketplace_store_facility_id', 'stkr_facility_fk')->references('id')->on('marketplace_store_facilities')->restrictOnDelete();
            $table->foreign('product_variant_id', 'stkr_variant_fk')->references('id')->on('product_variants')->restrictOnDelete();
            $table->foreign('order_id', 'stkr_order_fk')->references('id')->on('marketplace_orders')->restrictOnDelete();
            $table->foreign('actor_id', 'stkr_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_stock_take_order_reviews');
    }
};
