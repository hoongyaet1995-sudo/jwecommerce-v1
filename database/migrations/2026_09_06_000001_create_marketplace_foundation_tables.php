<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_stores', function (Blueprint $table) {
            $table->id();
            $table->string('platform',20);
            $table->string('shop_name',150);
            $table->string('external_shop_id',100)->nullable();
            $table->string('region',10);
            $table->string('environment',20);
            $table->string('status',30)->default('pending_auth');
            $table->timestamps();
            $table->unique(['platform','environment','external_shop_id'],'mp_store_identity');
        });
        Schema::create('marketplace_listing_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('external_product_id',100);
            $table->string('external_variant_id',100);
            $table->json('observed_data');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['store_id','external_product_id','external_variant_id'],'mp_variant_identity');
        });
        Schema::create('marketplace_variant_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_variant_id')->unique()->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('marketplace_mapping_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_variant_id')->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->unsignedBigInteger('old_variant_id')->nullable();
            $table->unsignedBigInteger('new_variant_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_mapping_events');
        Schema::dropIfExists('marketplace_variant_mappings');
        Schema::dropIfExists('marketplace_listing_variants');
        Schema::dropIfExists('marketplace_stores');
    }
};
