<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_variant_facility_scope', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('marketplace_store_id');
            $table->unsignedBigInteger('marketplace_store_facility_id');
            $table->string('status', 24);
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique(['product_variant_id', 'marketplace_store_facility_id'], 'variant_facility_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_variant_facility_scope');
    }
};
