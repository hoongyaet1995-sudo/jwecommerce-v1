<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_order_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('status', 30)->default('queued');
            $table->unsignedBigInteger('time_from');
            $table->unsignedBigInteger('time_to');
            $table->text('next_cursor')->nullable();
            $table->unsignedInteger('pages_completed')->default(0);
            $table->unsignedInteger('orders_processed')->default(0);
            $table->string('error_code')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('marketplace_order_runs'); }
};
