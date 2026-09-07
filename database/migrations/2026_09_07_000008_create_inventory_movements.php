<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->unsignedBigInteger('stock_version')->default(0));
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 128);
            $table->char('request_hash', 64);
            $table->integer('available_delta');
            $table->unsignedInteger('available_after');
            $table->unsignedBigInteger('stock_version');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 255);
            $table->timestamp('created_at');
            $table->unique(['product_variant_id','operation_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('stock_version'));
    }
};
