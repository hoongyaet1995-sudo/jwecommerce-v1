<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_catalog_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('next_offset')->default(0);
            $table->unsignedInteger('pages_completed')->default(0);
            $table->unsignedInteger('items_processed')->default(0);
            $table->unsignedInteger('variants_processed')->default(0);
            $table->string('error_code')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_catalog_runs'); }
};
