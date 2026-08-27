<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('label_path', 2048)->nullable()->change();
        });

        Schema::table('shipment_quotes', function (Blueprint $table): void {
            $table->renameColumn('idempotency_reference', 'reconciliation_reference');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_quotes', function (Blueprint $table): void {
            $table->renameColumn('reconciliation_reference', 'idempotency_reference');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('label_path')->nullable()->change();
        });
    }
};
