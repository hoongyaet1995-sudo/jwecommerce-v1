<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('fulfillment_method', 32)->nullable()->after('source');
            $table->string('driver_name', 120)->nullable()->after('tracking_url');
            $table->string('handoff_reference', 160)->nullable()->after('driver_name');
            $table->index(['fulfillment_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex(['fulfillment_method', 'status']);
            $table->dropColumn(['fulfillment_method', 'driver_name', 'handoff_reference']);
        });
    }
};
