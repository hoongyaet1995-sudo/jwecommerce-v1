<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_count_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('physical_count');
            $table->unsignedBigInteger('balance_version');
            $table->unsignedBigInteger('legacy_stock_version');
            $table->string('reason',255);
            $table->dateTime('expires_at');
            $table->dateTime('created_at');
        });
        Schema::create('inventory_physical_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('review_id')->unique();
            $table->foreign('review_id')->references('id')->on('inventory_count_reviews')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('physical_before')->nullable();
            $table->unsignedInteger('physical_after');
            $table->bigInteger('physical_delta')->nullable();
            $table->unsignedBigInteger('balance_version');
            $table->string('reason',255);
            $table->dateTime('created_at');
            $table->unique(['inventory_balance_id','balance_version'],'physical_movement_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_physical_movements');
        Schema::dropIfExists('inventory_count_reviews');
    }
};
