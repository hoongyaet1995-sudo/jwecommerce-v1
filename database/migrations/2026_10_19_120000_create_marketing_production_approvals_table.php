<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs reach this migration before productions/revisions exist.
        // A later additive migration creates the constrained table after its dependencies.
        if (!Schema::hasTable('marketing_productions') || !Schema::hasTable('marketing_revisions')) return;
        Schema::create('marketing_production_approvals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('production_id')->unique()->constrained('marketing_productions');
            $t->foreignUuid('revision_id')->constrained('marketing_revisions');
            $t->string('output_sha256', 64);
            $t->foreignId('approved_by')->constrained('users');
            $t->dateTime('approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_production_approvals');
    }
};
