<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('notification_dispatches')->exists()) {
            throw new RuntimeException(
                'Notification snapshot format cannot be inferred for existing rows. Deployment aborted before changes: notification_dispatches must be empty for this unreleased feature. Stop notification workers and follow the notification operations runbook before retrying.',
            );
        }

        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('snapshot_version')->default(2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('snapshot_version')->default(1)->change();
        });
    }
};
