<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_mode_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('configuration_version');
            $table->string('source_mode',32)->nullable();
            $table->string('target_mode',32);
            $table->string('strategy',32);
            $table->string('reason',255);
            $table->char('snapshot_hash',64);
            $table->json('allocation_plan');
            $table->json('review_snapshot');
            $table->json('receipt')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('committed_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['actor_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_mode_reviews');
    }
};
