<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('easyparcel_shipment_recoveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('confirmed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('easyparcel_shipment_recoveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('confirmed_by')->nullable(false)->change();
        });
    }
};
