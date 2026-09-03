<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('policy_window_days')->nullable()->after('eligible_until');
            $table->text('policy_summary_snapshot')->nullable()->after('policy_window_days');
            $table->string('external_refund_reference')->nullable()->after('resolved_refund_id');
            $table->text('manual_refund_note')->nullable()->after('external_refund_reference');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropColumn(['policy_window_days', 'policy_summary_snapshot', 'external_refund_reference', 'manual_refund_note']);
        });
    }
};
