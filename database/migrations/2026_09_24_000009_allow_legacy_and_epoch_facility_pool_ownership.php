<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->uuid('rebaseline_epoch_id')->nullable()
                ->constrained('inventory_rebaseline_epochs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_balances', fn (Blueprint $table) =>
            $table->dropConstrainedForeignId('rebaseline_epoch_id'));
    }
};
