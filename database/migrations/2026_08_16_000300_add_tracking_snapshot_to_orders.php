<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('tracking_carrier')->nullable()->after('shipping_label');
            $table->string('tracking_number')->nullable()->after('tracking_carrier');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['tracking_carrier', 'tracking_number', 'shipped_at']);
        });
    }
};
