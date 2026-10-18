<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', fn (Blueprint $t) => $t->string('summary', 500)->nullable());
    }

    public function down(): void
    {
        Schema::table('page_revisions', fn (Blueprint $t) => $t->dropColumn('summary'));
    }
};
