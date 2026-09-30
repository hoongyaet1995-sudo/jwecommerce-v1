<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('readback_attempts')->default(0);
            $table->timestamp('next_readback_at')->nullable();
            $table->timestamp('readback_exhausted_at')->nullable();
            $table->index(['status', 'next_readback_at'], 'store_sync_readback_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_store_sync_tasks', function (Blueprint $table): void {
            $table->dropIndex('store_sync_readback_due_index');
            $table->dropColumn(['readback_attempts', 'next_readback_at', 'readback_exhausted_at']);
        });
    }
};
