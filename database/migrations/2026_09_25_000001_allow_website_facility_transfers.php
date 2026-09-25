<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_facility_transfer_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_facility_id')->nullable()->change();
            $table->unsignedBigInteger('destination_facility_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('marketplace_facility_transfer_reviews')
            ->whereNull('source_facility_id')->orWhereNull('destination_facility_id')->exists()) {
            throw new RuntimeException('Cannot remove the mixed transfer route while its audit records exist.');
        }
        Schema::table('marketplace_facility_transfer_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_facility_id')->nullable(false)->change();
            $table->unsignedBigInteger('destination_facility_id')->nullable(false)->change();
        });
    }
};
