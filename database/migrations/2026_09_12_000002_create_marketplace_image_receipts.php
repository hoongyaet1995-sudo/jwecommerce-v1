<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_image_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('image_id')->constrained('product_images')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->char('source_hash',64);
            $table->text('provider_identity');
            $table->timestamps();
            $table->unique(['store_id','image_id','source_hash'],'marketplace_image_source_identity');
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_image_receipts'); }
};
