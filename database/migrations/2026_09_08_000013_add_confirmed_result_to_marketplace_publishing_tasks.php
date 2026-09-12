<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->json('confirmed_result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_publishing_tasks', function (Blueprint $table) {
            $table->dropColumn('confirmed_result');
        });
    }
};
