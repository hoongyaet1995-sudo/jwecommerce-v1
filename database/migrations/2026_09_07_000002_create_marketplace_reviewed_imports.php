<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_reviewed_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('external_product_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('result');
            $table->timestamps();
            $table->unique(['store_id', 'external_product_id'], 'marketplace_reviewed_import_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reviewed_imports');
    }
};
