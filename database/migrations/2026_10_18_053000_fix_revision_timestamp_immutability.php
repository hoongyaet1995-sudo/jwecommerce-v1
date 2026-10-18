<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', fn (Blueprint $t) => $t->dateTime('created_at')->change());
        // Pre-release QA databases may contain future dates from implicit MySQL timestamp updates.
        // Re-record those snapshots at this migration checkpoint; do not invent their lost original time.
        DB::table('page_revisions')->where('created_at', '>', now())->update(['created_at' => now()]);
    }

    public function down(): void
    { /* Snapshot timestamps must never gain automatic update behavior again. */
    }
};
