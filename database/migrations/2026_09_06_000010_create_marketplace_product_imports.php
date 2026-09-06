<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('external_product_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->timestamps();
            $table->unique(['store_id', 'external_product_id'], 'marketplace_import_source_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_product_imports'); }
};
