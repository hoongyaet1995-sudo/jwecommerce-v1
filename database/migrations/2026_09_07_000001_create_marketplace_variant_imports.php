<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_variant_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_variant_id')->unique()->constrained('marketplace_listing_variants')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('marketplace_variant_imports'); }
};
