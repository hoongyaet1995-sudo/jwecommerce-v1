<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->char('source_revision', 64)->nullable();
            $table->json('source_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_publishing_tasks', fn (Blueprint $table) => $table->dropColumn(['source_revision','source_snapshot']));
    }
};
