<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('snapshot_version')->default(1)->after('template_version');
            $table->unsignedSmallInteger('queue_attempt')->nullable()->after('attempt_count');
            $table->uuid('queue_generation')->nullable()->after('queue_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->dropColumn(['snapshot_version', 'queue_attempt', 'queue_generation']);
        });
    }
};
