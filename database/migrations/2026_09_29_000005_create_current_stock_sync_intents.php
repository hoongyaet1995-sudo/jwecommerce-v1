<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_current_stock_sync_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_balance_id');
            $table->foreign('inventory_balance_id', 'current_stock_intent_balance_fk')
                ->references('id')->on('inventory_balances')->restrictOnDelete();
            $table->unsignedBigInteger('balance_version');
            $table->string('event_key', 160);
            $table->unique('event_key', 'current_stock_intent_event_unique');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'id'], 'current_stock_intent_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_current_stock_sync_intents');
    }
};
