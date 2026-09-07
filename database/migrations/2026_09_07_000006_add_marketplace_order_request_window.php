<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_order_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_time_from')->nullable();
            $table->unsignedBigInteger('requested_time_to')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('marketplace_order_runs', fn (Blueprint $table) => $table->dropColumn(['requested_time_from', 'requested_time_to']));
    }
};
