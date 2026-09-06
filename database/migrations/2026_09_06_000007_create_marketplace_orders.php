<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('external_order_id', 100);
            $table->string('platform_status', 60);
            $table->unsignedBigInteger('platform_updated_at');
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 18, 4)->nullable();
            $table->longText('encrypted_payload');
            $table->dateTime('fetched_at');
            $table->timestamps();
            $table->unique(['store_id', 'external_order_id'], 'mp_order_identity');
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_orders'); }
};
