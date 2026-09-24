<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_rebaseline_epochs', function (Blueprint $table): void {
            $table->string('cutover_operation_key', 120)->nullable()->unique();
            $table->json('cutover_receipt')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_rebaseline_epochs', function (Blueprint $table): void {
            $table->dropUnique(['cutover_operation_key']);
            $table->dropColumn(['cutover_operation_key', 'cutover_receipt', 'reviewed_at']);
        });
    }
};
