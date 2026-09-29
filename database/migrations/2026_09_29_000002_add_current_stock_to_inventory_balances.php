<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->unsignedInteger('current_stock')->nullable();
        });
        Schema::create('inventory_current_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('event_key', 160)->unique();
            $table->char('request_hash', 64);
            $table->integer('delta');
            $table->unsignedInteger('current_after');
            $table->unsignedBigInteger('balance_version');
            $table->string('reason', 255);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_current_stock_movements');
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropColumn('current_stock'));
    }
};
